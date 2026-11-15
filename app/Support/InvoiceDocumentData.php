<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\ProformaInvoice;
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
            'quotations',
        ]);

        $company = $invoice->company;
        $branch = $invoice->sourceBranch ?: $company?->branch;
        $customer = $invoice->customer;

        $subtotal = (float) $invoice->subtotal;
        $taxAmount = (float) $invoice->tax_amount;
        $totalAmount = (float) $invoice->total_amount;

        // an invoice raised from an order keeps the service tax on the order, not on itself: taken from the order(s)
        // when it is exactly what separates the charges from the total
        if ($taxAmount <= 0) {
            $orderTax = (float) ($invoice->quotations->sum('tax_amount') ?: $invoice->quotation?->tax_amount);

            if ($orderTax > 0 && abs($subtotal + $orderTax - $totalAmount) < 0.05) {
                $taxAmount = $orderTax;
            }
        }

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
                // a paid Cash Bill / COD Invoice reads PAID at the right of the title, in place of CASH / COD; unpaid it
                // keeps its payment type; the Invoice always reads CREDIT / TERM (client, 13 Nov 2026 — this replaces
                // the PAID above the barcode)
                'payment_label' => in_array($invoice->type, ['cash_bill', 'cod'], true) && $invoice->status === \App\Enums\InvoiceStatus::Paid->value
                    ? 'PAID'
                    : $invoice->paymentLabel(),
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
            'totals' => $this->totals($totalAmount, $taxAmount, $subtotal, $taxRate),
            'amount_in_words' => AmountInWords::ringgit($totalAmount),
            'disclaimer' => config('og.invoice.disclaimer'),
            'bank_accounts' => config('og.invoice.bank_accounts', []),
            // the e-Invoice QR (client, 6 Nov 2026): the LHDN validated link once the submission has a UUID, else blank
            'einvoice_qr' => $this->einvoiceQr($invoice),
        ];
    }

    /**
     * The four lines under every billing document (client, 16 Nov 2026): Transport Charges (Excluding Tax),
     * Service Tax 6%, Rounding Adj., Total. The rounding is the few cents between charges + tax and the total; any
     * other difference stays in the charges, so the lines always add up to the total.
     *
     * @return array{subtotal_excl_tax: string, tax_label: string, tax_amount: string, rounding: string, total: string}
     */
    public function totals(float $total, float $tax, ?float $charges = null, ?int $taxRate = null): array
    {
        $charges ??= $total - $tax;
        $rounding = round($total - $charges - $tax, 2);

        if (abs($rounding) >= 0.05) {
            $rounding = 0.0;
        }

        return [
            'subtotal_excl_tax' => number_format(round($total - $tax - $rounding, 2), 2),
            // the rate charged, else the system's rate
            'tax_label' => 'Service Tax '.($taxRate ?: rtrim(rtrim(number_format(QuotationMatrix::sstRate(), 2, '.', ''), '0'), '.')).'%',
            'tax_amount' => number_format($tax, 2),
            'rounding' => number_format($rounding, 2),
            'total' => number_format($total, 2),
        ];
    }

    /**
     * The proforma invoice in the format of the Cash Bill, Invoice and COD Invoice (client, 16 Nov 2026): one row per
     * consignment note of the order — or one row for the order while it has no consignment note yet.
     *
     * @return array{lines: list<array<string, mixed>>, totals: array<string, string>, amount_in_words: string}
     */
    public function fromProforma(ProformaInvoice $proforma): array
    {
        $proforma->loadMissing(['quotation.lines', 'quotation.fromLocation', 'quotation.toLocation', 'consignmentNote']);

        $quotation = $proforma->quotation;
        $total = (float) $proforma->total_amount;
        $tax = (float) ($quotation?->tax_amount ?? 0);
        $charges = round($total - $tax, 2);

        $csns = $quotation
            ? $quotation->consignmentNotes()->with(['fromLocation', 'toLocation', 'deliveryOrder', 'lines'])->orderBy('id')->get()
            : collect([$proforma->consignmentNote])->filter()->values();

        if ($csns->isNotEmpty()) {
            $lines = $csns
                ->map(fn (ConsignmentNote $csn) => $this->lineFromCsn($csn, $csns->count() === 1 ? $charges : 0.0))
                ->map(fn (array $line) => $csns->count() === 1 ? [...$line, 'amount' => number_format($charges, 2)] : $line)
                ->values()->all();
        } else {
            $date = $proforma->issued_at ?? $proforma->created_at;
            $refs = collect([
                filled($quotation?->customer_do_number) ? (string) $quotation->customer_do_number : null,
                ...OrderStops::doNumbers($quotation?->stops),
            ])->filter()->unique()->values()->all();

            $lines = [[
                'date' => $date?->format('d/m/Y') ?? '—',
                'csn_number' => '—',
                'items' => $this->goods($quotation?->lines ?? collect()),
                'your_ref' => $refs ?: ['—'],
                'from' => $quotation?->fromLocation?->name ?: '—',
                'to' => $quotation?->toLocation?->name ?: '—',
                'consignee' => $quotation?->consignee_name ?: '—',
                'amount' => number_format($charges, 2),
            ]];
        }

        return [
            'lines' => $lines,
            'totals' => $this->totals($total, $tax),
            'amount_in_words' => AmountInWords::ringgit($total),
        ];
    }

    /**
     * The goods of a row, short enough for one line (client, 16 Nov 2026): the quantity and its unit — "3 CTN",
     * "3 PALLET", "1 LOAD" — and the item's name only when it has no unit. The pick-up / drop-off / minimum charge
     * lines are not goods (client, 30 Oct 2026).
     *
     * @return list<string>
     */
    private function goods(iterable $lines): array
    {
        return collect($lines)
            ->reject(fn ($line) => in_array(trim((string) $line->item_name), \App\Domains\Quotation\Actions\CreateOrderFromEnquiry::CHARGE_LINES, true))
            ->map(fn ($line) => QuantityLabel::unit($line->uom) !== null
                ? QuantityLabel::format($line->quantity, $line->uom)
                : trim(QuantityLabel::format($line->quantity, null).' × '.$line->item_name))
            ->values()->all();
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

        $items = $this->goods($csn->lines);

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
