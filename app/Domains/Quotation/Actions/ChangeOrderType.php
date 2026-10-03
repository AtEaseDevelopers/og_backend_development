<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use InvalidArgumentException;

/**
 * Section C: Term orders may change to Term / Cash / COD; COD may change only to Cash;
 * Cash cannot change. Not allowed once billing has been issued.
 */
class ChangeOrderType
{
    public function execute(Quotation $quotation, OrderType $to, User $actor, ?string $reason = null): Quotation
    {
        if ($quotation->status === QuotationStatus::Converted || $quotation->billingStatus() === BillingStatus::Generated) {
            throw new InvalidArgumentException('Billing has been issued; the order type can no longer be changed.');
        }

        $current = $quotation->orderType();

        if ($current === null) {
            // first assignment: anything is allowed (respecting a non-credit customer cannot be Term)
            if ($to === OrderType::Term && ! $quotation->customer?->is_credit) {
                throw new InvalidArgumentException('Only credit customers can have Credit / Term orders.');
            }
        } elseif (! $current->canChangeTo($to)) {
            throw new InvalidArgumentException(sprintf(
                'Order type %s cannot be changed to %s (allowed: %s).',
                $current->getLabel(),
                $to->getLabel(),
                collect($current->allowedTransitions())->map->getLabel()->implode(', '),
            ));
        }

        if ($current === $to) {
            return $quotation;
        }

        $updates = ['order_type' => $to];

        if ($quotation->status === QuotationStatus::Confirmed) {
            $updates['billing_status'] = match ($to) {
                OrderType::Cash => $quotation->isFullyPaid() ? BillingStatus::NotStarted : BillingStatus::AwaitingPayment,
                OrderType::Cod => $quotation->cod_blocked ? BillingStatus::AwaitingRelease : BillingStatus::NotStarted,
                OrderType::Term => $quotation->isReleased() ? BillingStatus::NotStarted : BillingStatus::AwaitingRelease,
            };
        }

        $quotation->update($updates);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $quotation->status->value,
            'to_status' => $quotation->status->value,
            'user_id' => $actor->id,
            'remarks' => sprintf('Order type changed %s → %s%s', $current?->getLabel() ?? '—', $to->getLabel(), $reason ? ': '.$reason : ''),
        ]);

        return $quotation->fresh();
    }
}
