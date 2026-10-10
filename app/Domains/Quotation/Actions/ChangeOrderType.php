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
 * Payment term of an order record: any (Credit / Term for credit customers) until the customer confirms or a
 * payment is recorded; after that it is fixed (Quotation::allowedOrderTypes / paymentTermLocked).
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
        } elseif ($current !== $to && $quotation->paymentTermLocked()) {
            throw new InvalidArgumentException('The payment term of '.$quotation->number.' can no longer change: the customer has confirmed or a payment has been recorded.');
        } elseif (! $quotation->canChangeOrderTypeTo($to)) {
            throw new InvalidArgumentException(sprintf(
                'Payment term %s cannot be changed to %s (allowed: %s).',
                $current->getLabel(),
                $to->getLabel(),
                collect($quotation->allowedOrderTypes())->map->getLabel()->implode(', '),
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

        // waiting for credit approval and no longer Credit / Term: the approval is not needed any more
        app(ReleaseCreditGate::class)->execute($quotation, $actor, 'payment term changed to '.$to->getLabel());

        return $quotation->fresh();
    }
}
