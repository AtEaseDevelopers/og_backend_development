<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateProformaForQuotation;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section P: a Credit / Term order held by the credit check (waiting for, or refused by, the Branch Manager)
 * may go ahead as a Cash order instead. The pending approval request is cancelled; the order is confirmed and
 * waits for payment. This is the one change allowed after the payment term is otherwise fixed.
 */
class SwitchRestrictedCreditToCash
{
    public static function allowed(Quotation $quotation): bool
    {
        if ($quotation->orderType() !== OrderType::Term || (float) $quotation->paid_amount > 0) {
            return false;
        }

        if ($quotation->status === QuotationStatus::PendingApproval) {
            return true;
        }

        return $quotation->status === QuotationStatus::Rejected
            && $quotation->isLatestVersion()
            && $quotation->creditApprovalRequests()->latest('id')->value('status') === 'rejected';
    }

    public function execute(Quotation $quotation, User $actor): Quotation
    {
        if (! self::allowed($quotation)) {
            throw new InvalidArgumentException('This order cannot be switched to Cash.');
        }

        DB::transaction(function () use ($quotation, $actor) {
            $from = $quotation->status;

            CreditApprovalRequest::query()
                ->where('quotation_id', $quotation->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'remarks' => 'Customer chose to pay cash instead', 'decided_at' => now()]);

            $quotation->update([
                'order_type' => OrderType::Cash,
                'payment_method' => null,
                'status' => QuotationStatus::Confirmed,
                'confirmed_at' => $quotation->confirmed_at ?? now(),
                'rejection_reason' => null,
                'billing_status' => $quotation->isFullyPaid() ? BillingStatus::NotStarted : BillingStatus::AwaitingPayment,
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from->value,
                'to_status' => QuotationStatus::Confirmed->value,
                'user_id' => $actor->id,
                'remarks' => 'Credit restricted: customer switched the order from Credit / Term to Cash in the portal',
            ]);
        });

        app(GenerateProformaForQuotation::class)->execute($quotation->fresh());

        return $quotation->fresh();
    }
}
