<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use Illuminate\Support\Carbon;

class InvoiceDocumentData
{
    /**
     * @return array<string, mixed>
     */
    public function fromInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'company.branch',
            'sourceBranch',
            'customer',
            'lines.consignmentNote.fromLocation',
            'lines.consignmentNote.toLocation',
            'lines.consignmentNote.deliveryOrder',
            'lines.consignmentNote.lines',
            'quotation.portalEnquiry',
        ]);

        $company = $invoice->company;
        $branch = $invoice->sourceBranch ?: $company?->branch;
        $customer = $invoice->customer;

        $subtotal = (float) $invoice->subtotal;
        $taxAmount = (float) $invoice->tax_amount;
        $totalAmount = (float) $invoice->total_amount;
        $taxRate = $subtotal > 0 && $taxAmount > 0
            ? (int) round(($taxAmount / $subtotal) * 100)
            : null;

        $terms = $customer?->credit_term_days
            ? $customer->credit_term_days.' Days'
            : match ($invoice->type) {
                'cash_bill' => 'Cash',
                default => '—',
            };

        return [
            'letterhead' => [
                // no logo (user, 30 Oct 2026): the company's details only
                'logo_src' => null,
                'company_name' => $company?->name ?: $branch?->company_name ?: 'O&G Transport',
                'reg_no' => $company?->brn ?: $branch?->company_no,
                'address' => $company?->address ?: $branch?->address,
                'phone' => $company?->phone ?: $branch?->phone,
                'email' => $company?->email ?: $branch?->email,
                'website' => config('app.url'),
            ],
            'meta' => [
                'number' => $invoice->number,
                'invoice_date' => $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'account_no' => $customer?->code ?: $customer?->control_account ?: '—',
                'terms' => $terms,
                // the order and due date (user, 30 Oct 2026: every important detail on the document)
                'order_no' => $invoice->quotation?->orderNumber(),
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'type' => $invoice->isCashBill() ? 'Cash Bill' : ucwords(str_replace('_', ' ', (string) $invoice->type)),
            ],
            'bill_to' => [
                'name' => $customer?->company_name ?: '—',
                'reg_no' => $customer?->brn,
                'address' => $customer?->address ?: '—',
                'attention' => $invoice->quotation?->attention ?: $customer?->attention,
                'phone' => $invoice->quotation?->customer_pic_phone ?: $customer?->phone,
                'email' => $customer?->email,
            ],
            'lines' => $this->lines($invoice),
            'totals' => [
                'subtotal_excl_tax' => $this->formatAmount($subtotal),
                'tax_label' => $taxRate ? "Add SST@{$taxRate}%" : 'Add SST',
                'tax_amount' => $this->formatAmount($taxAmount),
                'total' => $this->formatAmount($totalAmount),
            ],
            'amount_in_words' => AmountInWords::ringgit($totalAmount),
            'disclaimer' => config('og.invoice.disclaimer'),
            'bank_accounts' => config('og.invoice.bank_accounts', []),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lines(Invoice $invoice): array
    {
        return $invoice->lines
            ->map(function ($line) {
                $csn = $line->consignmentNote;

                if ($csn) {
                    return $this->lineFromCsn($csn, (float) $line->amount);
                }

                return [
                    'date' => '—',
                    'csn_number' => '—',
                    'your_ref' => [$line->description ?: '—'],
                    'from' => '—',
                    'to' => '—',
                    'sst' => '',
                    'amount' => $this->formatAmount((float) $line->amount),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function lineFromCsn(ConsignmentNote $csn, float $fallbackAmount): array
    {
        $lineSubtotal = (float) $csn->subtotal;
        $lineTax = (float) $csn->tax_amount;

        if ($lineSubtotal <= 0 && $fallbackAmount > 0) {
            $lineSubtotal = max(0, $fallbackAmount - $lineTax);
        }

        if ($lineSubtotal <= 0 && $fallbackAmount > 0) {
            $lineSubtotal = $fallbackAmount;
            $lineTax = 0;
        }

        $refs = collect([
            filled($csn->do_number) ? 'DO No. '.$csn->do_number : null,
            filled($csn->customer_reference) ? 'Ref. '.$csn->customer_reference : null,
            ...collect($csn->other_do_numbers ?? [])
                ->filter()
                ->map(fn (string $ref) => 'DO No. '.$ref)
                ->all(),
        ])->filter()->unique()->values()->all();

        if ($refs === []) {
            $refs = ['—'];
        }

        $date = $csn->issued_at
            ?? $csn->deliveryOrder?->delivered_at
            ?? $csn->job_date;

        if ($date && ! $date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        // the goods on the CSN (user, 30 Oct 2026): "15 × 1100L Recycle Bin @ 130.00"
        $items = $csn->lines->map(fn ($line) => trim(QuantityLabel::format($line->quantity, $line->uom).' × '.$line->item_name
            .($line->unit_price !== null ? ' @ '.number_format((float) $line->unit_price, 2) : '')))->values()->all();

        return [
            'date' => $date?->format('d/m/Y') ?? '—',
            'csn_number' => $csn->number,
            'items' => $items,
            'your_ref' => $refs,
            'from' => $csn->fromLocation?->name ?: '—',
            'to' => $csn->toLocation?->name ?: $csn->delivery_city ?: '—',
            'sst' => $lineTax > 0 ? $this->formatAmount($lineTax) : '',
            'amount' => $this->formatAmount($lineSubtotal > 0 ? $lineSubtotal : $fallbackAmount),
        ];
    }

    private function formatAmount(float $amount): string
    {
        if ($amount <= 0) {
            return '';
        }

        return number_format($amount, 2);
    }
}
