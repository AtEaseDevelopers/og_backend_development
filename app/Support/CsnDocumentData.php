<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Company;
use Illuminate\Support\Carbon;

class CsnDocumentData
{
    /**
     * @return array<string, mixed>
     */
    public function fromConsignmentNote(ConsignmentNote $csn): array
    {
        $csn->loadMissing([
            'company.branch',
            'sourceBranch',
            'fromLocation',
            'toLocation',
            'lines',
            'deliveryOrder.lorry',
            'deliveryOrder.jobSheet',
            'invoice',
            'proformaInvoice',
            'customer',
        ]);

        $matrixState = app(CsnTransportMatrix::class)->toFormState($csn);
        $chargeColumn = $csn->delivery_city ?: $csn->consignee_name ?: ($matrixState['matrix_columns'][0] ?? null);
        $lines = app(CsnTransportMatrix::class)->linesFromMatrix(
            $matrixState['matrix_columns'],
            $matrixState['matrix_rows'],
            $chargeColumn,
        );

        return $this->build(
            company: $csn->company,
            branch: $csn->sourceBranch,
            fields: [
                'number' => $csn->number,
                'issued_at' => $csn->issued_at,
                'do_number' => $csn->do_number ?: $csn->deliveryOrder?->number,
                'invoice_number' => $csn->invoice?->number ?: $csn->proformaInvoice?->number,
                'lorry_number' => $csn->deliveryOrder?->lorry?->registration_no,
                'job_sheet_number' => $csn->deliveryOrder?->jobSheet?->number ?: $csn->job_no,
                // the QR carries the CSN number (user, 30 Oct 2026: scanned into the search fields); the old token QRs still work
                'qr_value' => $csn->number,
                // the billing customer (user, 30 Oct 2026)
                'customer_name' => $csn->customer_name ?: $csn->customer?->company_name,
                'customer_brn' => $csn->customer_brn ?: $csn->customer?->brn,
                'customer_address' => $csn->customer?->address,
                'customer_contact' => $this->dropOffContact($csn->customer?->attention, $csn->customer_phone ?: $csn->customer?->phone),
                'consignor_phone' => $csn->consignor_phone,
                // every consignee (the main one and the extra drop-off stops), each to chop & sign
                'consignees' => $this->consignees($csn),
                // '' = left blank on the order (shown as "—"); not set at all (older CSNs) = the customer
                'consignor_name' => $csn->consignor_name === '' ? null : ($csn->consignor_name ?: $csn->customer_name),
                'consignor_address' => $csn->consignor_address,
                'consignee_name' => $csn->consignee_name,
                'consignee_pic' => $csn->consignee_pic,
                'consignee_phone' => $csn->consignee_phone,
                'delivery_address' => $csn->delivery_address,
                'delivery_postcode' => $csn->delivery_postcode,
                'delivery_city' => $csn->delivery_city,
                'delivery_state' => $csn->delivery_state,
                'from_location' => $csn->fromLocation?->name
                    ?? app(OrderListingData::class)->cityFromAddress($csn->consignor_address)
                    ?? $csn->sourceBranch?->name,
                'to_location' => $csn->toLocation?->name ?? ($csn->delivery_city ?: null),
                'lines' => $lines,
                'transport_charges' => (float) $csn->transport_charges,
                'subtotal' => (float) $csn->subtotal,
                'discount' => (float) $csn->discount,
                'tax_amount' => (float) $csn->tax_amount,
                'total_amount' => (float) $csn->total_amount,
                'remarks' => $csn->remarks,
                'marking' => $csn->marking,
            ],
        );
    }

    /**
     * The consignees of a CSN: the main one (its delivery address, PIC and DO) and each extra drop-off stop.
     *
     * @return list<array{name: ?string, address: ?string, contact: ?string, do_number: ?string}>
     */
    private function consignees(ConsignmentNote $csn): array
    {
        $main = [
            'name' => $csn->consignee_name,
            'address' => $this->addressBlock($csn->delivery_address, $csn->delivery_postcode, $csn->delivery_city, $csn->delivery_state, $csn->consignee_name) ?: null,
            'contact' => $this->dropOffContact($csn->consignee_pic, $csn->consignee_phone),
            'do_number' => $csn->customer_do_number ?: null,
        ];
        $stops = collect($csn->stops ?? [])
            ->filter(fn ($stop) => is_array($stop) && ($stop['type'] ?? '') !== OrderStops::PICKUP)
            ->map(fn (array $stop) => ['name' => $csn->consignee_name, 'address' => $stop['location'] ?? null, 'contact' => null, 'do_number' => $stop['do_number'] ?? null])
            ->values()
            ->all();

        return [$main, ...$stops];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function fromPreviewFields(array $fields, ?Company $company = null): array
    {
        return $this->build(
            company: $company ?? CurrentCompany::get(),
            branch: null,
            fields: $fields,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function build(?Company $company, mixed $branch, array $fields): array
    {
        $branch = $branch ?: $company?->branch;

        $companyName = $company?->name ?: $branch?->company_name ?: 'O&G Transport';
        $regNo = $company?->brn ?: $branch?->company_no;

        $lines = collect($fields['lines'] ?? [])
            ->filter(fn (array $line) => filled($line['item_name'] ?? null))
            ->map(function (array $line) {
                $qty = (float) ($line['quantity'] ?? 0);

                return [
                    'quantity' => $qty > 0 ? $this->formatQuantity($qty) : '—',
                    'description' => '- '.($line['item_name'] ?? '—'),
                    'amount_myr' => $this->nullableAmount($line['line_total'] ?? null),
                    'sst_myr' => null,
                    'total_myr' => $this->nullableAmount($line['line_total'] ?? null),
                ];
            })
            ->values()
            ->all();

        $transportCharges = (float) ($fields['transport_charges'] ?? 0);
        if ($lines === [] && $transportCharges > 0) {
            $lines[] = [
                'quantity' => '—',
                'description' => '- Transport charges',
                'amount_myr' => $this->formatAmount($transportCharges),
                'sst_myr' => null,
                'total_myr' => $this->formatAmount($transportCharges),
            ];
        }

        $totalMyr = (float) ($fields['total_amount'] ?? 0);
        if ($totalMyr <= 0) {
            $totalMyr = collect($lines)->sum(fn (array $line) => $this->parseAmount($line['total_myr']));
        }

        $dropOffAddress = $this->addressBlock(
            $fields['delivery_address'] ?? null,
            $fields['delivery_postcode'] ?? null,
            $fields['delivery_city'] ?? null,
            $fields['delivery_state'] ?? null,
            $fields['consignee_name'] ?? null,
        );

        $issuedAt = $fields['issued_at'] ?? null;
        if ($issuedAt && ! $issuedAt instanceof Carbon) {
            $issuedAt = Carbon::parse($issuedAt);
        }

        return [
            // no logo (user, 30 Oct 2026): the company's details only
            'letterhead' => [
                'logo_src' => null,
                'logo_url' => null,
                'company_name' => $companyName,
                'reg_no' => $regNo,
                'address' => $company?->address ?: $branch?->address,
                'phone' => $company?->phone ?: $branch?->phone,
                'email' => $company?->email ?: $branch?->email,
            ],
            'meta' => [
                'number' => $fields['number'] ?? '—',
                'issued_at' => $issuedAt?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'do_number' => filled($fields['do_number'] ?? null) ? $fields['do_number'] : '—',
                'invoice_number' => filled($fields['invoice_number'] ?? null) ? $fields['invoice_number'] : '—',
                'lorry_number' => filled($fields['lorry_number'] ?? null) ? $fields['lorry_number'] : '—',
                'job_sheet_number' => filled($fields['job_sheet_number'] ?? null) ? $fields['job_sheet_number'] : '—',
                'qr_value' => $fields['qr_value'] ?? ($fields['number'] ?? null),
            ],
            'bill_to' => [
                'name' => $fields['customer_name'] ?? '—',
                'reg_no' => $fields['customer_brn'] ?? null,
                'address' => $fields['customer_address'] ?? null,
                'contact' => $fields['customer_contact'] ?? null,
            ],
            'consignor' => [
                'name' => $fields['consignor_name'] ?? '—',
                'address' => $fields['consignor_address'] ?? '—',
                'contact' => $fields['consignor_phone'] ?? null,
            ],
            'consignees' => $fields['consignees'] ?? [[
                'name' => $fields['consignee_name'] ?? '—',
                'address' => $dropOffAddress ?: null,
                'contact' => $this->dropOffContact($fields['consignee_pic'] ?? null, $fields['consignee_phone'] ?? null),
                'do_number' => $fields['customer_do_number'] ?? null,
            ]],
            'consignee' => [
                'name' => $fields['consignee_name'] ?? '—',
                'address' => $dropOffAddress ?: '—',
            ],
            'route' => [
                'from' => $fields['from_location'] ?? '—',
                'to' => $fields['to_location'] ?? '—',
            ],
            'lines' => $lines,
            'drop_off' => [
                'address' => $dropOffAddress ?: '—',
                'contact' => $this->dropOffContact(
                    $fields['consignee_pic'] ?? null,
                    $fields['consignee_phone'] ?? null,
                ),
            ],
            'totals' => [
                'amount_myr' => $this->formatAmount((float) ($fields['subtotal'] ?? 0)),
                'sst_myr' => $this->formatAmount((float) ($fields['tax_amount'] ?? 0)),
                'total_myr' => $this->formatAmount($totalMyr),
            ],
            'footer' => [
                'remarks' => $fields['remarks'] ?? null,
                'marking' => $fields['marking'] ?? null,
                'ccp_no' => null,
                'k2_no' => null,
                'declaration_receipt_no' => null,
            ],
        ];
    }

    /**
     * Address lines without repeats: skips a street line that only repeats the consignee or the
     * city, and city/state/postcode parts that already appear in the street line.
     */
    private function addressBlock(?string $street, ?string $postcode, ?string $city, ?string $state, ?string $consigneeName): string
    {
        $street = trim((string) $street);
        $norm = fn (?string $v) => mb_strtolower(trim((string) $v));

        if ($street !== '' && in_array($norm($street), [$norm($consigneeName), $norm($city), $norm($state)], true)) {
            $street = '';
        }

        $tail = collect([$postcode, $city, $state])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->reject(fn ($v) => $street !== '' && str_contains(mb_strtolower($street), mb_strtolower($v)))
            ->unique(fn ($v) => mb_strtolower($v))
            ->implode(', ');

        return collect([$street, $tail])->filter()->implode("\n");
    }

    private function formatUom(?string $uom): string
    {
        if (! filled($uom)) {
            return 'Box';
        }

        return ucfirst(strtolower($uom));
    }

    private function formatQuantity(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }

    private function formatAmount(float $amount): string
    {
        if ($amount <= 0) {
            return '';
        }

        return number_format($amount, 2);
    }

    private function nullableAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return $this->formatAmount((float) $amount);
    }

    private function parseAmount(?string $amount): float
    {
        if (! filled($amount)) {
            return 0;
        }

        return (float) str_replace(',', '', $amount);
    }

    private function dropOffContact(?string $pic, ?string $phone): ?string
    {
        $parts = array_filter([
            filled($pic) ? $pic : null,
            filled($phone) ? $phone : null,
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }
}
