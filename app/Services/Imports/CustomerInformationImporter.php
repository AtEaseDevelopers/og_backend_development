<?php

namespace App\Services\Imports;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Imports the client's "Customer Information" workbook (user, 25 Oct 2026) into Master Data → Customers: the columns
 * are found by their titles (Debtor Code, CompanyName, AreaCode, Debtor Type, Display Term, Aging On, Statement Type,
 * Company Registration No., Address1–4, PostCode, DeliverAddr1–4, Deliver PostCode, Attention, Phone1, Phone2, Mobile,
 * Contact Person 3 / Phone 3, Contact Person 4 / Phone 4, GST No., SST No., TaxCode, TIN No., EmailAddress, the three
 * own notes and msic1–3). A customer already there (same debtor code) is updated. Uploaded on the Customers page, so
 * the client's customer data never sits in the code.
 *
 * Replace: the customers that are not in the file and have no orders, CSNs, invoices or payments are removed; those
 * that have them are set inactive (their records keep the right customer), so they are no longer offered.
 */
class CustomerInformationImporter
{
    private const COLUMNS = [
        'debtorcode' => 'code',
        'companyname' => 'company_name',
        'areacode' => 'area',
        'debtortype' => 'debtor_type',
        'displayterm' => 'display_term',
        'agingon' => 'aging_on',
        'statementtype' => 'statement_type',
        'companyregistrationno' => 'brn',
        'address1' => 'address_1',
        'address2' => 'address_2',
        'address3' => 'address_3',
        'address4' => 'address_4',
        'postcode' => 'postcode',
        'deliveraddr1' => 'deliver_1',
        'deliveraddr2' => 'deliver_2',
        'deliveraddr3' => 'deliver_3',
        'deliveraddr4' => 'deliver_4',
        'deliverpostcode' => 'deliver_postcode',
        'attention' => 'attention',
        'phone1' => 'phone',
        'phone2' => 'phone_2',
        'mobile' => 'mobile',
        'contactperson3' => 'contact_person_3',
        'phone3' => 'phone_3',
        'contactperson4' => 'contact_person_4',
        'phone4' => 'phone_4',
        'gstno' => 'gst_no',
        'sstno' => 'sst_registration_no',
        'taxcode' => 'tax_type',
        'tinno' => 'tin',
        'emailaddress' => 'email',
        'ownnoteotheremailstatementremark' => 'own_note',
        'mailingstatementremark' => 'mailing_statement_remark',
        'ownnotepayment' => 'payment_note',
        'msic1' => 'msic_code',
        'msic2' => 'msic_code_2',
        'msic3' => 'msic_code_3',
    ];

    /** The records that keep a customer (it is set inactive instead of removed). */
    private const IN_USE = ['quotations', 'consignment_notes', 'invoices', 'proforma_invoices', 'payments', 'receipts', 'refund_notes', 'payment_submissions', 'portal_enquiries', 'quotes', 'statements', 'credit_approval_requests'];

    public function __construct(private SimpleXlsxReader $reader) {}

    /** @return array{created: int, updated: int, removed: int, deactivated: int, skipped: list<string>} */
    public function importFile(string $path, ?int $companyId, ?int $branchId, bool $replace = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0, 'deactivated' => 0, 'skipped' => []];

        DB::transaction(function () use ($path, $companyId, $branchId, $replace, &$result) {
            $codes = [];

            foreach ($this->reader->readSheetsByRow($path) as $sheet => $rows) {
                $map = null;

                foreach ($rows as $rowNumber => $cells) {
                    if ($map === null) {
                        $map = $this->headerMap($cells);

                        if (! in_array('code', $map, true) || ! in_array('company_name', $map, true)) {
                            continue 2; // not a customer sheet
                        }

                        continue;
                    }

                    $row = [];

                    foreach ($map as $column => $field) {
                        $row[$field] = $this->clean($cells[$column] ?? null);
                    }

                    if ($row['code'] === null || ($row['company_name'] ?? null) === null) {
                        if (array_filter($row) !== []) {
                            $result['skipped'][] = "{$sheet} row {$rowNumber}: no debtor code or company name";
                        }

                        continue;
                    }

                    $codes[] = $row['code'];
                    $this->save($row, $companyId, $branchId, $result);
                }
            }

            if ($codes === []) {
                throw new RuntimeException('No customers found: the workbook needs a sheet with the Debtor Code and CompanyName columns.');
            }

            if ($replace) {
                $this->replace($codes, $companyId, $result);
            }
        });

        return $result;
    }

    /** @param  array<string, string|null>  $row */
    private function save(array $row, ?int $companyId, ?int $branchId, array &$result): void
    {
        $customer = Customer::query()
            ->when($companyId, fn ($q, $id) => $q->where('company_id', $id))
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($row['code'])])
            ->first() ?? new Customer(['company_id' => $companyId, 'branch_id' => $branchId, 'currency' => 'MYR']);
        $days = $this->termDays($row['display_term'] ?? null);

        $customer->fill(array_filter([
            'code' => $row['code'],
            'company_name' => $row['company_name'],
            'area' => $row['area'] ?? null,
            'debtor_type' => $row['debtor_type'] ?? null,
            'display_term' => $row['display_term'] ?? null,
            'aging_on' => match (strtoupper((string) ($row['aging_on'] ?? ''))) { 'I' => 'invoice_date', 'D' => 'due_date', default => null },
            'statement_type' => match (strtoupper((string) ($row['statement_type'] ?? ''))) { 'O' => 'open_item', 'B' => 'balance_forward', 'N' => 'no_statement', default => null },
            'brn' => $row['brn'] ?? null,
            'address' => $this->lines($row, 'address_') ?? null,
            'postcode' => $row['postcode'] ?? null,
            'attention' => $row['attention'] ?? null,
            'phone' => $row['phone'] ?? null,
            'phone_2' => $row['phone_2'] ?? null,
            'mobile' => $row['mobile'] ?? null,
            'contact_person_3' => $row['contact_person_3'] ?? null,
            'phone_3' => $row['phone_3'] ?? null,
            'contact_person_4' => $row['contact_person_4'] ?? null,
            'phone_4' => $row['phone_4'] ?? null,
            'gst_no' => $row['gst_no'] ?? null,
            'sst_registration_no' => $row['sst_registration_no'] ?? null,
            'tax_type' => $row['tax_type'] ?? null,
            'tin' => $row['tin'] ?? null,
            'einvoice_tin' => $row['tin'] ?? null,
            'email' => $row['email'] ?? null,
            'own_note' => $row['own_note'] ?? null,
            'mailing_statement_remark' => $row['mailing_statement_remark'] ?? null,
            'payment_note' => $row['payment_note'] ?? null,
            'msic_code' => $row['msic_code'] ?? null,
            'msic_code_2' => $row['msic_code_2'] ?? null,
            'msic_code_3' => $row['msic_code_3'] ?? null,
            'einvoice_buyer_name' => $row['company_name'],
        ], fn ($value) => $value !== null));

        // "30 DAYS" → a 30-day credit customer; C.O.D. / cash → no credit
        if ($days !== null) {
            $customer->credit_term_days = $days;
            $customer->is_credit = $days > 0;
        }

        $customer->status = 'active';
        $created = ! $customer->exists;
        $customer->save();
        $result[$created ? 'created' : 'updated']++;

        if (($delivery = $this->lines($row, 'deliver_')) !== null) {
            CustomerAddress::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'type' => 'delivery', 'is_default' => true],
                ['label' => 'Primary delivery', 'address' => $delivery, 'postcode' => $row['deliver_postcode'] ?? null],
            );
        }
    }

    /** @param  list<string>  $codes */
    private function replace(array $codes, ?int $companyId, array &$result): void
    {
        $keep = array_map('strtoupper', $codes);
        $others = Customer::query()
            ->when($companyId, fn ($q, $id) => $q->where('company_id', $id))
            ->get(['id', 'code'])
            ->reject(fn (Customer $customer) => in_array(strtoupper(trim((string) $customer->code)), $keep, true));

        foreach ($others as $customer) {
            $inUse = collect(self::IN_USE)
                ->filter(fn (string $table) => Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id'))
                ->contains(fn (string $table) => DB::table($table)->where('customer_id', $customer->id)->exists());

            if ($inUse) {
                Customer::query()->whereKey($customer->id)->where('status', '!=', 'inactive')->update(['status' => 'inactive', 'updated_at' => now()])
                    && $result['deactivated']++;

                continue;
            }

            $customer->delete();
            $result['removed']++;
        }
    }

    /** @param  array<int|string, string|null>  $cells @return array<int|string, string> */
    private function headerMap(array $cells): array
    {
        $map = [];

        foreach ($cells as $column => $title) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $title));

            if (isset(self::COLUMNS[$key])) {
                $map[$column] = self::COLUMNS[$key];
            }
        }

        return $map;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return in_array($value, ['', '-', '—'], true) ? null : $value;
    }

    /** @param  array<string, string|null>  $row */
    private function lines(array $row, string $prefix): ?string
    {
        $lines = array_filter([$row[$prefix.'1'] ?? null, $row[$prefix.'2'] ?? null, $row[$prefix.'3'] ?? null, $row[$prefix.'4'] ?? null]);

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function termDays(?string $term): ?int
    {
        if ($term === null) {
            return null;
        }

        if (preg_match('/(\d+)/', $term, $m)) {
            return (int) $m[1];
        }

        return preg_match('/c\.?o\.?d|cash/i', $term) ? 0 : null;
    }
}
