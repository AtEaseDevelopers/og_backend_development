<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\ProceedNonCashOrderToCsn;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Credit approval belongs to Credit / Term orders only. An order waiting for it (customer already confirmed)
 * whose payment term is now Cash or COD no longer needs it: the pending request is cancelled and the order is
 * confirmed as if it had been confirmed with that payment term (Cash: awaiting payment; COD: on to invoice and
 * CSN unless blocked).
 */
class ReleaseCreditGate
{
    /** @return bool whether the order was waiting for credit approval and has been released */
    public function execute(Quotation $quotation, ?User $actor = null, string $why = 'payment term is no longer Credit / Term'): bool
    {
        $quotation->refresh();
        $type = $quotation->orderType();

        if ($quotation->status !== QuotationStatus::PendingApproval || $type === OrderType::Term || $type === null) {
            return false;
        }

        DB::transaction(function () use ($quotation, $actor, $type, $why): void {
            CreditApprovalRequest::query()
                ->where('quotation_id', $quotation->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'remarks' => 'Not needed: '.$why, 'decided_at' => now()]);

            $quotation->update([
                'status' => QuotationStatus::Confirmed,
                'confirmed_at' => $quotation->confirmed_at ?? now(),
                'billing_status' => match ($type) {
                    OrderType::Cash => $quotation->isFullyPaid() ? BillingStatus::NotStarted : BillingStatus::AwaitingPayment,
                    OrderType::Cod => $quotation->cod_blocked ? BillingStatus::AwaitingRelease : BillingStatus::NotStarted,
                },
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => QuotationStatus::PendingApproval->value,
                'to_status' => QuotationStatus::Confirmed->value,
                'user_id' => $actor?->id,
                'remarks' => 'Credit approval no longer needed ('.$why.') · order confirmed as '.$type->getLabel(),
            ]);
        });

        // like a COD order confirmed by the customer: invoice and CSN follow (Admin can still block COD)
        if ($type === OrderType::Cod) {
            app(ProceedNonCashOrderToCsn::class)->execute($quotation->fresh(), $actor);
        }

        return true;
    }
}
