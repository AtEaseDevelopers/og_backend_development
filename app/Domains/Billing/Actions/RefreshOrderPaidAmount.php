<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\OrderType;

/**
 * Keeps an order's paid amount equal to the sum of its completed payments, whichever flow recorded them:
 * an approved payment submission, a payment edit, the driver's COD collection or a CSN "Collect Payment".
 * Shared by RecordPayment and ReviewPaymentSubmission (no circular dependency between the two).
 */
class RefreshOrderPaidAmount
{
    /** Sum of the completed payments recorded against the order — the live paid amount. */
    public static function livePaid(Quotation $order): float
    {
        return round((float) Payment::query()
            ->where('quotation_id', $order->getKey())
            ->where('status', 'completed')
            ->sum('amount'), 2);
    }

    public static function liveOutstanding(Quotation $order): float
    {
        return max(0, round((float) $order->total_amount - self::livePaid($order), 2));
    }

    /**
     * True while the order's amount is still to be collected by the driver: a live COD CSN without a completed payment
     * against it, or a COD order with no CSN yet (it becomes a COD CSN for the full amount). An upfront payment does
     * not reduce what the driver must collect (CompleteDelivery requires the full CSN total), so the customer would
     * pay twice.
     */
    public static function codCollectionPending(Quotation $order): bool
    {
        $csns = ConsignmentNote::query()
            ->where('quotation_id', $order->getKey())
            ->where('status', '!=', CsnStatus::Cancelled->value)
            ->get(['id', 'billing_type']);

        if ($csns->isEmpty()) {
            return $order->orderType() === OrderType::Cod;
        }

        $codIds = $csns->filter(fn (ConsignmentNote $csn) => $csn->billing_type === CsnBillingType::Cod)->pluck('id');

        if ($codIds->isEmpty()) {
            return false;
        }

        $collected = Payment::query()
            ->whereIn('consignment_note_id', $codIds)
            ->where('status', 'completed')
            ->distinct()
            ->pluck('consignment_note_id');

        return $codIds->diff($collected)->isNotEmpty();
    }

    public function execute(Quotation $quotation): void
    {
        $paid = self::livePaid($quotation);

        $quotation->update([
            'paid_amount' => $paid,
            'billing_status' => $quotation->billingStatus() === BillingStatus::Generated
                ? BillingStatus::Generated
                : ($quotation->orderType() === OrderType::Cash && $paid + 0.005 < (float) $quotation->total_amount
                    ? BillingStatus::AwaitingPayment
                    : $quotation->billingStatus()),
        ]);

        $quotation->proformaInvoice?->update(['paid_amount' => $paid]);

        // a COD order with its CSN gets the COD invoice once fully paid and every driver collection is verified
        // (COD Reconciliation); payments recorded by Admin are approved already
        if ($quotation->orderType() === OrderType::Cod
            && $paid + 0.005 >= (float) $quotation->total_amount && (float) $quotation->total_amount > 0
            && ! Payment::query()->where('quotation_id', $quotation->id)->where('status', 'completed')->where('method', 'cod')
                ->where(fn ($q) => $q->whereNull('reconciliation_status')->orWhere('reconciliation_status', 'pending'))->exists()
            && ! $quotation->invoices()->where('type', 'cod')->where('status', '!=', 'cancelled')->exists()
            && $quotation->consignmentNotes()->where('status', '!=', CsnStatus::Cancelled->value)->exists()) {
            app(GenerateOrderBilling::class)->issueInvoice($quotation, auth()->user());
        }
    }
}
