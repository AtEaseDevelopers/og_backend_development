<?php

use App\Services\DocumentNumberingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The existing documents renumbered in O&G's format (client's list, user 11 Nov 2026), as new ones are numbered
 * (DocumentNumberingService): oldest first, a running number per company and location code that does not restart with
 * the month; the date part is the document's own. The copies of a number are updated with it (a CSN's invoice /
 * proforma / job sheet number, a subsheet's CSN part, a refund note's invoice) and the counters continue after the
 * renumbered documents. Every change is kept in document_number_changes (old → new), which down() puts back.
 *
 * Not renumbered (no O&G format on the client's list): delivery orders, break bulk, commission batches / slips,
 * subsheets without a transfer code. Texts of past messages (notification log, status history) keep the old numbers.
 */
return new class extends Migration
{
    /** @var array<int, int> company id → branch id */
    private array $companyBranch = [];

    /** @var array<string, int> "branch|key" → the last running number given */
    private array $counters = [];

    public function up(): void
    {
        if (! Schema::hasTable('document_number_changes')) {
            Schema::create('document_number_changes', function (Blueprint $table): void {
                $table->id();
                $table->string('table_name', 64);
                $table->unsignedBigInteger('record_id');
                $table->string('column_name', 64);
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->string('batch', 40);
                $table->timestamps();
                $table->index(['table_name', 'old_value']);
            });
        }

        // a quotation number QXX/DebtorCode/YYMM(-2) is longer than the old Q-KL-261000001
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('number', 60)->change();
        });

        $this->companyBranch = DB::table('companies')->pluck('branch_id', 'id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function (): void {
            $jobSheets = $this->jobSheets();
            $invoices = $this->invoices();
            $proformas = $this->proformas();
            $csns = $this->consignmentNotes($jobSheets, $invoices, $proformas);
            $this->subsheets($csns);
            $this->refundNotes($invoices);
            $this->orderQuotations();
            $this->quotes();
            $this->orderForms();
            $this->receipts();
            $this->paymentVouchers();
            $this->purchaseOrders();
            $this->saveCounters();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_number_changes')) {
            return;
        }

        DB::transaction(function (): void {
            $changes = DB::table('document_number_changes')->where('batch', 'og-format-2611')->orderByDesc('id')->get();

            // two passes so a restored number never meets a renumbered one that has not been put back yet
            foreach ($changes as $change) {
                DB::table($change->table_name)->where('id', $change->record_id)->update([$change->column_name => 'TMP-'.$change->id]);
            }

            foreach ($changes as $change) {
                DB::table($change->table_name)->where('id', $change->record_id)->update([$change->column_name => $change->old_value]);
            }

            DB::table('document_number_sequences')->where('period', DocumentNumberingService::RUNNING)->delete();
            DB::table('document_number_changes')->where('batch', 'og-format-2611')->delete();
        });
    }

    /* ------------------------------------------------------------------ the documents ------------------------------------------------------------------ */

    /** Job sheets: XX#### (KL0001). @return array<string, string> old → new */
    private function jobSheets(): array
    {
        $rows = DB::table('job_sheets')->orderBy('operating_date')->orderBy('id')->get(['id', 'number', 'company_id']);

        return $this->renumber('job_sheets', 'number', $rows, function ($row) {
            $branch = $this->companyBranch[(int) $row->company_id] ?? 0;
            $code = $this->code($branch);

            return $code.str_pad((string) $this->nextCounter($branch, 'JS:'.$code), 4, '0', STR_PAD_LEFT);
        });
    }

    /** Invoices: CBXXYYMM##### (cash bill), CODXXYYMM##### (COD invoice), IVYYMM##### (invoice). @return array<int, array<string, string>> company → old → new */
    private function invoices(): array
    {
        $rows = DB::table('invoices')->orderByRaw('coalesce(invoice_date, date(created_at))')->orderBy('id')->get(['id', 'number', 'type', 'company_id', 'source_branch_id', 'invoice_date', 'created_at']);
        $byCompany = [];

        $map = $this->renumber('invoices', 'number', $rows, function ($row) {
            $branch = (int) ($row->source_branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $code = $this->code($branch);
            $ym = Carbon::parse($row->invoice_date ?? $row->created_at)->format('ym');

            return match ($row->type) {
                'cash_bill' => 'CB'.$code.$ym.$this->pad($this->nextCounter($branch, 'CBL:'.$code), 5),
                'cod' => 'COD'.$code.$ym.$this->pad($this->nextCounter($branch, 'CIV:'.$code), 5),
                default => 'IV'.$ym.$this->pad($this->nextCounter($branch, 'INV'), 5),
            };
        }, keyed: true);

        foreach ($rows as $row) {
            if (isset($map[$row->id])) {
                $byCompany[(int) $row->company_id][$row->number] = $map[$row->id];
            }
        }

        return $byCompany;
    }

    /** Proforma invoices: PIXXYYMM#####. @return array<string, string> old → new */
    private function proformas(): array
    {
        $rows = DB::table('proforma_invoices')->orderByRaw('coalesce(issued_at, created_at)')->orderBy('id')->get(['id', 'number', 'company_id', 'source_branch_id', 'issued_at', 'created_at']);

        return $this->renumber('proforma_invoices', 'number', $rows, function ($row) {
            $branch = (int) ($row->source_branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $code = $this->code($branch);

            return 'PI'.$code.Carbon::parse($row->issued_at ?? $row->created_at)->format('ym').$this->pad($this->nextCounter($branch, 'PI:'.$code), 5);
        });
    }

    /**
     * Consignment notes: XXYYMM##### (the code and month already in the number), a running number per code across
     * companies; with them their invoice / proforma copies and the job sheet number. @return array<int, array{0: string, 1: string}> id → [old, new]
     */
    private function consignmentNotes(array $jobSheets, array $invoices, array $proformas): array
    {
        $rows = DB::table('consignment_notes')->get(['id', 'number', 'company_id', 'source_branch_id', 'invoice_number', 'proforma_number', 'job_no', 'issued_at', 'created_at'])
            ->map(function ($row) {
                // XX-YYMM##### (or XXYYMM#####): its code, month and running number
                $row->parts = preg_match('/^([A-Z]+)-?(\d{4})(\d+)$/', (string) $row->number, $m) ? [$m[1], $m[2], (int) $m[3]] : null;

                return $row;
            });
        $perCode = [];
        $changed = [];

        foreach ($rows->filter(fn ($row) => $row->parts !== null)->sortBy(fn ($row) => sprintf('%s|%s|%010d|%010d', $row->parts[0], $row->parts[1], $row->parts[2], $row->id)) as $row) {
            [$code, $ym] = $row->parts;
            $perCode[$code] = ($perCode[$code] ?? 0) + 1;
            $new = $code.$ym.$this->pad($perCode[$code], 5);
            $changed[$row->id] = [$row->number, $new];
            // the counter of every company that numbers CSNs with this code continues after the highest one
            $branch = (int) ($row->source_branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $this->counters[$branch.'|CSN:'.$code] = max($this->counters[$branch.'|CSN:'.$code] ?? 0, $perCode[$code]);
        }

        foreach ($perCode as $code => $last) {
            foreach (array_keys($this->counters) as $key) {
                if (str_ends_with($key, '|CSN:'.$code)) {
                    $this->counters[$key] = $last;
                }
            }
        }

        $this->apply('consignment_notes', 'number', collect($changed)->map(fn ($pair) => $pair[1])->all(), collect($changed)->map(fn ($pair) => $pair[0])->all());

        // the job sheet the CSN is on (its delivery order's), else none: a CSN no longer takes a job sheet number
        $sheetOf = DB::table('delivery_orders')->join('job_sheets', 'job_sheets.id', '=', 'delivery_orders.job_sheet_id')
            ->whereNotNull('delivery_orders.consignment_note_id')->orderBy('delivery_orders.id')
            ->pluck('job_sheets.number', 'delivery_orders.consignment_note_id');

        foreach ($rows as $row) {
            $updates = [];
            $company = (int) $row->company_id;

            if (filled($row->invoice_number) && isset($invoices[$company][$row->invoice_number])) {
                $updates['invoice_number'] = $invoices[$company][$row->invoice_number];
            }

            if (filled($row->proforma_number) && isset($proformas[$row->proforma_number])) {
                $updates['proforma_number'] = $proformas[$row->proforma_number];
            }

            $sheet = $sheetOf[$row->id] ?? null;
            $updates['job_no'] = $sheet ?: null;

            foreach ($updates as $column => $value) {
                if ($value !== $row->{$column}) {
                    $this->log('consignment_notes', $row->id, $column, $row->{$column}, $value);
                    DB::table('consignment_notes')->where('id', $row->id)->update([$column => $value]);
                }
            }
        }

        return $changed;
    }

    /** Subsheets with a transfer code: the CSN number + code, so the CSN part follows the CSN's new number. */
    private function subsheets(array $csns): void
    {
        $rows = DB::table('subsheets')->get(['id', 'number', 'consignment_note_id']);
        $map = [];
        $old = [];

        foreach ($rows as $row) {
            [$from, $to] = $csns[$row->consignment_note_id] ?? [null, null];

            if ($from && str_starts_with((string) $row->number, $from)) {
                $map[$row->id] = $to.substr((string) $row->number, strlen($from));
                $old[$row->id] = $row->number;
            }
        }

        $this->apply('subsheets', 'number', $map, $old);
    }

    /** Refund notes: RN######## per company, and the invoice they knock off. */
    private function refundNotes(array $invoices): void
    {
        $rows = DB::table('refund_notes')->orderBy('created_at')->orderBy('id')->get(['id', 'number', 'company_id', 'source_branch_id', 'knock_off_invoice_number']);

        $this->renumber('refund_notes', 'number', $rows, function ($row) {
            $branch = (int) ($row->source_branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));

            return 'RN'.$this->pad($this->nextCounter($branch, 'RN'), 8);
        });

        foreach ($rows as $row) {
            $new = $invoices[(int) $row->company_id][$row->knock_off_invoice_number] ?? null;

            if ($new && $new !== $row->knock_off_invoice_number) {
                $this->log('refund_notes', $row->id, 'knock_off_invoice_number', $row->knock_off_invoice_number, $new);
                DB::table('refund_notes')->where('id', $row->id)->update(['knock_off_invoice_number' => $new]);
            }
        }
    }

    /** Order quotations: QXX######## per company; a revision keeps its "-V2" after the new number. */
    private function orderQuotations(): void
    {
        $rows = DB::table('quotations')->orderBy('created_at')->orderBy('id')->get(['id', 'number', 'branch_id', 'company_id']);
        $bases = [];
        $map = [];
        $old = [];

        foreach ($rows as $row) {
            [$base, $suffix] = preg_match('/^(.*?)(-V\d+)$/', (string) $row->number, $m) ? [$m[1], $m[2]] : [(string) $row->number, ''];

            if (! isset($bases[$base])) {
                $branch = (int) ($row->branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
                $code = $this->code($branch);
                $bases[$base] = 'Q'.$code.$this->pad($this->nextCounter($branch, 'QT:'.$code), 8);
            }

            $map[$row->id] = $bases[$base].$suffix;
            $old[$row->id] = $row->number;
        }

        $this->apply('quotations', 'number', $map, $old);
    }

    /** Quotations with exclusive prices: QXX/DebtorCode/YYMM (lead L+id, no customer GENERAL; a second one -2). */
    private function quotes(): void
    {
        $rows = DB::table('quotes')->leftJoin('customers', 'customers.id', '=', 'quotes.customer_id')
            ->orderByRaw('coalesce(quotes.quote_date, date(quotes.created_at))')->orderBy('quotes.id')
            ->get(['quotes.id', 'quotes.number', 'quotes.branch_id', 'quotes.company_id', 'quotes.lead_id', 'quotes.customer_id', 'quotes.quote_date', 'quotes.created_at', 'customers.code as debtor']);
        $taken = [];
        $map = [];
        $old = [];

        foreach ($rows as $row) {
            $branch = (int) ($row->branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $party = strtoupper(trim((string) $row->debtor)) ?: ($row->customer_id ? 'C'.$row->customer_id : ($row->lead_id ? 'L'.$row->lead_id : 'GENERAL'));
            $base = 'Q'.$this->code($branch).'/'.$party.'/'.Carbon::parse($row->quote_date ?? $row->created_at)->format('ym');
            $number = $base;
            $n = 1;

            while (isset($taken[$number])) {
                $number = $base.'-'.(++$n);
            }

            $taken[$number] = true;
            $map[$row->id] = $number;
            $old[$row->id] = $row->number;
        }

        $this->apply('quotes', 'number', $map, $old);
    }

    /** Order numbers (the order form): OFXXYYMM##### per company. */
    private function orderForms(): void
    {
        $rows = DB::table('portal_enquiries')->whereNotNull('order_number')->where('order_number', '!=', '')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'order_number', 'branch_id', 'company_id', 'created_at']);

        $this->renumber('portal_enquiries', 'order_number', $rows, function ($row) {
            $branch = (int) ($row->branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $code = $this->code($branch);

            return 'OF'.$code.Carbon::parse($row->created_at)->format('ym').$this->pad($this->nextCounter($branch, 'ORD:'.$code), 5);
        }, column: 'order_number');
    }

    /** Official receipts: ORYYMM##### per company. */
    private function receipts(): void
    {
        $rows = DB::table('receipts')->orderBy('created_at')->orderBy('id')->get(['id', 'number', 'source_branch_id', 'created_at']);

        $this->renumber('receipts', 'number', $rows, fn ($row) => 'OR'.Carbon::parse($row->created_at)->format('ym').$this->pad($this->nextCounter((int) $row->source_branch_id, 'OR'), 5));
    }

    /** COD payment vouchers: PVXXYYMMDD### per company. */
    private function paymentVouchers(): void
    {
        $rows = DB::table('payment_vouchers')->orderBy('created_at')->orderBy('id')->get(['id', 'number', 'company_id', 'source_branch_id', 'created_at']);

        $this->renumber('payment_vouchers', 'number', $rows, function ($row) {
            $branch = (int) ($row->source_branch_id ?: ($this->companyBranch[(int) $row->company_id] ?? 0));
            $code = $this->code($branch);

            return 'PV'.$code.Carbon::parse($row->created_at)->format('ymd').$this->pad($this->nextCounter($branch, 'PV:'.$code), 3);
        });
    }

    /** Commission purchase orders: YYMM/XX/lorry no (the batch's month), "-2" for a second one. */
    private function purchaseOrders(): void
    {
        $rows = DB::table('commission_purchase_orders as po')
            ->leftJoin('commission_slips as s', 's.id', '=', 'po.commission_slip_id')
            ->leftJoin('commission_batches as b', 'b.id', '=', 's.commission_batch_id')
            ->leftJoin('lorries as l', 'l.id', '=', 's.lorry_id')
            ->leftJoin('drivers as d', 'd.id', '=', 's.driver_id')
            ->orderBy('po.id')
            ->get(['po.id', 'po.po_number', 'po.source_branch_id', 'po.created_at', 'b.month', 'l.registration_no', 'd.name as driver']);
        $taken = [];
        $map = [];
        $old = [];

        foreach ($rows as $row) {
            $month = preg_match('/^\d{2}(\d{2})-(\d{2})$/', (string) $row->month, $m) ? $m[1].$m[2] : Carbon::parse($row->created_at)->format('ym');
            $lorry = strtoupper(preg_replace('/\s+/', '', (string) $row->registration_no)) ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($row->driver ?: 'DRIVER')));
            $base = $month.'/'.$this->code((int) $row->source_branch_id).'/'.$lorry;
            $number = $base;
            $n = 1;

            while (isset($taken[$number])) {
                $number = $base.'-'.(++$n);
            }

            $taken[$number] = true;
            $map[$row->id] = $number;
            $old[$row->id] = $row->po_number;
        }

        $this->apply('commission_purchase_orders', 'po_number', $map, $old);
    }

    /* ------------------------------------------------------------------ helpers ------------------------------------------------------------------ */

    /**
     * Numbers each row with $make (in the rows' order) and writes them. @return array<string|int, string> old → new
     * (or id → new when $keyed)
     */
    private function renumber(string $table, string $logColumn, Collection $rows, Closure $make, bool $keyed = false, ?string $column = null): array
    {
        $column ??= $logColumn;
        $map = [];
        $old = [];

        foreach ($rows as $row) {
            $map[$row->id] = $make($row);
            $old[$row->id] = $row->{$column};
        }

        $this->apply($table, $column, $map, $old);

        if ($keyed) {
            return $map;
        }

        $byOld = [];

        foreach ($map as $id => $new) {
            $byOld[(string) $old[$id]] = $new;
        }

        return $byOld;
    }

    /** Writes the new numbers in two passes (no unique number meets another on the way) and logs each change. */
    private function apply(string $table, string $column, array $map, array $old): void
    {
        $map = array_filter($map, fn ($new, $id) => $new !== ($old[$id] ?? null), ARRAY_FILTER_USE_BOTH);

        foreach (array_keys($map) as $id) {
            DB::table($table)->where('id', $id)->update([$column => 'TMP-'.$table.'-'.$id]);
        }

        foreach ($map as $id => $new) {
            DB::table($table)->where('id', $id)->update([$column => $new]);
            $this->log($table, $id, $column, $old[$id] ?? null, $new);
        }
    }

    private function log(string $table, int $id, string $column, ?string $old, ?string $new): void
    {
        DB::table('document_number_changes')->insert([
            'table_name' => $table, 'record_id' => $id, 'column_name' => $column,
            'old_value' => $old, 'new_value' => $new, 'batch' => 'og-format-2611',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function nextCounter(int $branch, string $key): int
    {
        return $this->counters[$branch.'|'.$key] = ($this->counters[$branch.'|'.$key] ?? 0) + 1;
    }

    /** The counters continue after the renumbered documents (DocumentNumberingService's running counters). */
    private function saveCounters(): void
    {
        // every company's counters start where its renumbered documents end, at 0 for a document it has none of yet
        // (so the first one is 1, not after the old monthly counters)
        foreach (array_unique(array_values($this->companyBranch)) as $branch) {
            $code = $this->code((int) $branch);

            foreach (['JS:'.$code, 'QT:'.$code, 'ORD:'.$code, 'PI:'.$code, 'CBL:'.$code, 'CIV:'.$code, 'PV:'.$code, 'INV', 'OR', 'RN'] as $type) {
                $this->counters[$branch.'|'.$type] ??= 0;
            }
        }

        foreach ($this->counters as $key => $last) {
            [$branch, $type] = explode('|', $key, 2);

            if ((int) $branch <= 0) {
                continue;
            }

            DB::table('document_number_sequences')->updateOrInsert(
                ['branch_id' => (int) $branch, 'document_type' => $type, 'period' => DocumentNumberingService::RUNNING],
                ['last_number' => $last, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    private function code(int $branch): string
    {
        return $branch > 0 ? DocumentNumberingService::locationCode($branch) : 'XX';
    }

    private function pad(int $n, int $width): string
    {
        return str_pad((string) $n, $width, '0', STR_PAD_LEFT);
    }
};
