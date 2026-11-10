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
                'cod' => 'COD',
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
                'sst_no' => ($company?->tin ?: $branch?->sst_no) ?? null,
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
                // the title and the payment type beside it (user, 11 Nov 2026): INVOICE · CREDIT / TERM, COD INVOICE · COD,
                // CASH BILL · CASH
                'title' => strtoupper($invoice->documentLabel()),
                'payment_label' => $invoice->paymentLabel(),
                'salesperson' => $invoice->quotation?->salesperson?->name,
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
            // the e-Invoice QR (client, 6 Nov 2026): the LHDN validated link once the submission has a UUID, else blank
            'einvoice_qr' => $this->einvoiceQr($invoice),
        ];
    }

    /** The QR of the LHDN validated link, when the invoice's e-Invoice submission carries a UUID. */
    private function einvoiceQr(Invoice $invoice): ?string
    {
        $uuid = $invoice->einvoiceSubmission?->uuid;

        if (! filled($uuid) || ! method_exists(\App\Support\BarcodeImage::class, 'qrDataUri')) {
            return null;
        }

        return \App\Support\BarcodeImage::qrDataUri('https://myinvois.hasil.gov.my/'.$uuid);
    }

    /** @return list<array<string, mixed>> */
    private function lines(Invoice $invoice): array
    {
        return $invoice->lines
            // one line per CSN (an invoice holding the same CSN several times prints it once)
            ->unique(fn ($line) => $line->consignment_note_id ? 'csn:'.$line->consignment_note_id : 'line:'.$line->id)
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
            // one statement in date order (client, 30 Oct 2026: 1/9 → 30/9), then by CSN number
            ->sortBy(fn (array $line) => [$line['sort_date'] ?? '9999-99-99', $line['csn_number'] ?? ''])
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

        // the customer's D/O numbers (client, 30 Oct 2026: not our delivery order's), one per drop-off
        $refs = collect([
            filled($csn->customer_do_number) ? (string) $csn->customer_do_number : null,
            ...OrderStops::doNumbers($csn->stops),
            filled($csn->customer_reference) ? 'Ref. '.$csn->customer_reference : null,
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

        // the goods on the CSN (user, 30 Oct 2026): "15 × 1100L Recycle Bin", with the measurement and weight the customer
        // keyed, never the price band's range (client, 6 Nov 2026) — no charges per line (client, 30 Oct 2026)
        $items = $csn->lines
            // the pick-up / drop-off / minimum charge lines are not goods (client, 30 Oct 2026)
            ->reject(fn ($line) => in_array(trim((string) $line->item_name), \App\Domains\Quotation\Actions\CreateOrderFromEnquiry::CHARGE_LINES, true))
            ->map(function ($line) {
                $measure = trim(implode(' · ', array_filter([$line->dimensions ?? null, ($line->weight ?? null) ? $line->weight.' kg' : null])));

                return trim(QuantityLabel::format($line->quantity, $line->uom).' × '.$line->item_name).($measure !== '' ? ' ('.$measure.')' : '');
            })->values()->all();

        return [
            'date' => $date?->format('d/m/Y') ?? '—',
            'sort_date' => $date?->format('Y-m-d'),
            'csn_number' => $csn->number,
            'items' => $items,
            'your_ref' => $refs,
            'from' => $csn->fromLocation?->name ?: '—',
            'to' => $csn->toLocation?->name ?: $csn->delivery_city ?: '—',
            'consignee' => $csn->consignee_name ?: '—',
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
