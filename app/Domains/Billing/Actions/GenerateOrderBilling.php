<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Section G + H: after payment completes or Admin releases the order, generate the
 * Invoice (Term / COD: one per billing record) or Cash Bill(s) (one per cash payment
 * transaction), then — only on success — automatically create the CSN(s) with status
 * "Pending Lorry Assignment". Failure keeps the order in "Billing Generation Failed"
 * and never creates a CSN; retry never duplicates an existing document.
 */
class GenerateOrderBilling
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private ConvertQuotationToCsns $convert,
        private SendNotification $notify,
    ) {}

    /** @return array{ok: bool, error: ?string, invoices: Collection<int, Invoice>, csns: Collection} */
    public function execute(Quotation $quotation, ?User $actor = null): array
    {
        $quotation->loadMissing(['customer', 'branch', 'lines', 'destinations', 'invoices', 'proformaInvoice']);
        $actor ??= User::query()->role('hq_admin')->first();

        if ($quotation->status === QuotationStatus::Converted && $quotation->billingStatus() === BillingStatus::Generated) {
            return ['ok' => true, 'error' => null, 'invoices' => $quotation->invoices, 'csns' => $quotation->consignmentNotes()->get()];
        }

        if (! $quotation->isEligibleForBilling()) {
            throw new InvalidArgumentException($quotation->billingBlockReason() ?? 'Order is not eligible for billing.');
        }

        try {
            $result = DB::transaction(function () use ($quotation, $actor) {
                $invoices = match ($quotation->orderType()) {
                    OrderType::Cash => $this->cashBills($quotation),
                    default => $this->invoice($quotation),
                };

                $quotation->update([
                    'billing_status' => BillingStatus::Generated,
                    'billing_error' => null,
                    'billed_at' => now(),
                ]);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'from_status' => $quotation->status->value,
                    'to_status' => $quotation->status->value,
                    'user_id' => $actor?->id,
                    'remarks' => 'Billing issued: '.$invoices->pluck('number')->implode(', '),
                ]);

                // Only after billing succeeded: create the CSN(s)
                $csns = $quotation->consignmentNotes()->exists()
                    ? $quotation->consignmentNotes()->get()
                    : $this->convert->execute(
                        $quotation->fresh(['customer', 'branch', 'lines', 'destinations', 'invoices', 'proformaInvoice']),
                        $actor,
                        $quotation->orderType()?->billingType()->value ?? 'cash_bill',
                    );

                // Link the billing documents to the (first) CSN for the legacy CSN views
                $firstCsn = $csns->first();

                if ($firstCsn) {
                    foreach ($invoices as $invoice) {
                        if (! $invoice->consignment_note_id) {
                            $invoice->update(['consignment_note_id' => $firstCsn->id]);
                            $invoice->lines()->whereNull('consignment_note_id')->update(['consignment_note_id' => $firstCsn->id]);
                        }
                    }

                    if ($quotation->proformaInvoice && ! $quotation->proformaInvoice->consignment_note_id) {
                        $quotation->proformaInvoice->update(['consignment_note_id' => $firstCsn->id]);
                    }

                    Payment::query()->where('quotation_id', $quotation->id)->whereNull('consignment_note_id')
                        ->update(['consignment_note_id' => $firstCsn->id]);
                }

                return ['invoices' => $invoices, 'csns' => $csns];
            });
        } catch (Throwable $e) {
            $quotation->update([
                'billing_status' => BillingStatus::Failed,
                'billing_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $quotation->status->value,
                'to_status' => $quotation->status->value,
                'user_id' => $actor?->id,
                'remarks' => 'Billing generation failed: '.mb_substr($e->getMessage(), 0, 500),
            ]);

            report($e);

            return ['ok' => false, 'error' => $e->getMessage(), 'invoices' => collect(), 'csns' => collect()];
        }

        $this->notifyCustomer($quotation->fresh(['customer']), $result['invoices'], $result['csns']);

        return ['ok' => true, 'error' => null, 'invoices' => $result['invoices'], 'csns' => $result['csns']];
    }

    /**
     * Cash orders: one Cash Bill per completed cash payment transaction (3 payments = 3 Cash Bills).
     * An admin release without a payment issues a single outstanding Cash Bill for the total.
     *
     * @return Collection<int, Invoice>
     */
    private function cashBills(Quotation $quotation): Collection
    {
        $payments = Payment::query()
            ->where('quotation_id', $quotation->id)
            ->where('status', 'completed')
            ->orderBy('id')
            ->get();

        $bills = collect();

        foreach ($payments as $payment) {
            $existing = Invoice::query()->where('payment_id', $payment->id)->first();

            if ($existing) {
                $bills->push($existing);

                continue;
            }

            $bills->push($this->createInvoice($quotation, 'cash_bill', (float) $payment->amount, InvoiceStatus::Paid, [
                ['description' => sprintf('Cash Bill — %s payment%s for order %s', ucfirst(str_replace('_', ' ', (string) $payment->method)), $payment->reference ? ' ref '.$payment->reference : '', $quotation->number), 'amount' => (float) $payment->amount],
            ], $payment));
        }

        if ($bills->isEmpty()) {
            $existing = $quotation->invoices->firstWhere('type', 'cash_bill');

            if ($existing) {
                return collect([$existing]);
            }

            if (! $quotation->isReleased()) {
                throw new InvalidArgumentException('No approved payment found for this cash order.');
            }

            $bills->push($this->createInvoice($quotation, 'cash_bill', (float) $quotation->total_amount, InvoiceStatus::Outstanding, $this->linesFromQuotation($quotation)));
        }

        return $bills;
    }

    /** @return Collection<int, Invoice> */
    private function invoice(Quotation $quotation): Collection
    {
        $type = $quotation->orderType() === OrderType::Cod ? 'cod' : 'term';
        $existing = $quotation->invoices->firstWhere('type', $type);

        if ($existing) {
            return collect([$existing]);
        }

        $status = (float) $quotation->paid_amount + 0.005 >= (float) $quotation->total_amount
            ? InvoiceStatus::Paid
            : ((float) $quotation->paid_amount > 0 ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Outstanding);

        return collect([$this->createInvoice($quotation, $type, (float) $quotation->total_amount, $status, $this->linesFromQuotation($quotation))]);
    }

    /** @return list<array{description: string, amount: float}> */
    private function linesFromQuotation(Quotation $quotation): array
    {
        $lines = $quotation->lines
            ->filter(fn ($line) => (float) $line->line_total > 0)
            ->map(fn ($line) => [
                'description' => trim($line->item_name.' x '.rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.').' '.($line->uom ?? '')),
                'amount' => (float) $line->line_total,
            ])
            ->values()
            ->all();

        return $lines !== [] ? $lines : [['description' => 'Transport charges — '.$quotation->number, 'amount' => (float) $quotation->total_amount]];
    }

    /** @param  list<array{description: string, amount: float}>  $lines */
    private function createInvoice(Quotation $quotation, string $type, float $total, InvoiceStatus $status, array $lines, ?Payment $payment = null): Invoice
    {
        $subtotal = round(collect($lines)->sum('amount'), 2);
        $rounded = round($total, 2);
        $dueDays = $type === 'term' ? (int) ($quotation->customer?->credit_term_days ?? 0) : 0;

        $invoice = Invoice::query()->create([
            'number' => $this->numbering->next($quotation->branch, DocumentType::Invoice),
            'company_id' => $quotation->company_id,
            'source_branch_id' => $quotation->branch_id,
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'proforma_invoice_id' => $quotation->proformaInvoice?->id,
            'payment_id' => $payment?->id,
            'type' => $type,
            'billing_month' => now()->format('Y-m'),
            'status' => $status->value,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'rounding_amount' => round($rounded - $subtotal, 2),
            'total_amount' => $rounded,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays($dueDays)->toDateString(),
        ]);

        foreach ($lines as $line) {
            $invoice->lines()->create(['description' => $line['description'], 'amount' => $line['amount']]);
        }

        if ($payment && ! $payment->invoice_id) {
            $payment->update(['invoice_id' => $invoice->id]);
        }

        return $invoice->load('lines');
    }

    private function notifyCustomer(Quotation $quotation, Collection $invoices, Collection $csns): void
    {
        $customer = $quotation->customer;
        $isCash = $quotation->orderType() === OrderType::Cash;

        $this->notify->execute(
            event: 'billing_issued',
            recipient: ['type' => 'customer', 'name' => $customer?->company_name, 'email' => $customer?->email, 'phone' => $customer?->phone],
            subject: ($isCash ? 'Cash Bill issued for ' : 'Invoice issued for ').$quotation->number,
            message: sprintf(
                "%s %s has been issued for order %s.\nConsignment note(s): %s\nYour delivery is now pending lorry assignment.",
                $isCash ? 'Cash Bill' : 'Invoice',
                $invoices->pluck('number')->implode(', '),
                $quotation->number,
                $csns->pluck('number')->implode(', ') ?: '—',
            ),
            related: $quotation,
        );
    }
}
