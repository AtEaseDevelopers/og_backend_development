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
            'invoice',
            'proformaInvoice',
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
                'consignor_name' => $csn->consignor_name ?: $csn->customer_name,
                'consignor_address' => $csn->consignor_address,
                'consignee_name' => $csn->consignee_name,
                'consignee_pic' => $csn->consignee_pic,
                'consignee_phone' => $csn->consignee_phone,
                'delivery_address' => $csn->delivery_address,
                'delivery_postcode' => $csn->delivery_postcode,
                'delivery_city' => $csn->delivery_city,
                'delivery_state' => $csn->delivery_state,
                'from_location' => $csn->fromLocation?->name,
                'to_location' => $csn->toLocation?->name,
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
                $uom = $this->formatUom($line['uom'] ?? null);

                return [
                    'quantity' => $qty > 0 ? trim($this->formatQuantity($qty).' '.$uom) : '—',
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

        $dropOffAddress = collect([
            $fields['delivery_address'] ?? null,
            collect([
                $fields['delivery_postcode'] ?? null,
                $fields['delivery_city'] ?? null,
                $fields['delivery_state'] ?? null,
            ])->filter()->implode(', '),
        ])->filter()->implode("\n");

        $issuedAt = $fields['issued_at'] ?? null;
        if ($issuedAt && ! $issuedAt instanceof Carbon) {
            $issuedAt = Carbon::parse($issuedAt);
        }

        $logoPath = public_path('images/logo-og.png');
        $logoSrc = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return [
            'letterhead' => [
                'logo_src' => $logoSrc,
                'logo_url' => asset('images/logo-og.png'),
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
            ],
            'consignor' => [
                'name' => $fields['consignor_name'] ?? '—',
                'address' => $fields['consignor_address'] ?? '—',
            ],
            'consignee' => [
                'name' => $fields['consignee_name'] ?? '—',
                'address' => collect([
                    $fields['delivery_address'] ?? null,
                    collect([
                        $fields['delivery_postcode'] ?? null,
                        $fields['delivery_city'] ?? null,
                        $fields['delivery_state'] ?? null,
                    ])->filter()->implode(', '),
                ])->filter()->implode("\n") ?: '—',
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
