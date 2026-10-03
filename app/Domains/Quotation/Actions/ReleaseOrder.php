<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use InvalidArgumentException;

/**
 * Section F: authorised Admin release of a partially paid / credit-term / COD order so
 * that the Invoice or Cash Bill can be generated and the CSN created. Also COD block /
 * unblock control. Every override records user, reason and timestamp.
 */
class ReleaseOrder
{
    public function __construct(
        private GenerateOrderBilling $billing,
        private SendNotification $notify,
    ) {}

    /** @return array{ok: bool, error: ?string, quotation: Quotation} */
    public function execute(Quotation $quotation, User $actor, string $reason): array
    {
        $this->assertAuthorised($actor);

        if ($quotation->status !== QuotationStatus::Confirmed) {
            throw new InvalidArgumentException('Only confirmed orders can be released.');
        }

        if ($quotation->isReleased()) {
            throw new InvalidArgumentException('This order was already released on '.$quotation->released_at?->format('d/m/Y H:i').'.');
        }

        if (blank($reason)) {
            throw new InvalidArgumentException('A release reason is required.');
        }

        $quotation->update([
            'released_at' => now(),
            'released_by' => $actor->id,
            'release_reason' => $reason,
            'release_outstanding' => $quotation->outstandingAmount(),
            'cod_blocked' => false,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $quotation->status->value,
            'to_status' => $quotation->status->value,
            'user_id' => $actor->id,
            'remarks' => sprintf('Admin release by %s — outstanding RM %s. Reason: %s', $actor->name, number_format($quotation->outstandingAmount(), 2), $reason),
        ]);

        $quotation->loadMissing('customer');

        $this->notify->execute(
            event: 'admin_release_approved',
            recipient: ['type' => 'customer', 'name' => $quotation->customer?->company_name, 'email' => $quotation->customer?->email, 'phone' => $quotation->customer?->phone],
            subject: 'Order '.$quotation->number.' released for processing',
            message: 'Your order '.$quotation->number.' has been approved for processing by our Admin. Outstanding balance: RM '.number_format($quotation->outstandingAmount(), 2).'.',
            related: $quotation,
        );

        $result = $this->billing->execute($quotation->fresh(), $actor);

        return ['ok' => $result['ok'], 'error' => $result['error'], 'quotation' => $quotation->fresh()];
    }

    public function blockCod(Quotation $quotation, User $actor, string $reason): Quotation
    {
        $this->assertAuthorised($actor);

        $quotation->update([
            'cod_blocked' => true,
            'cod_block_reason' => $reason,
            'cod_blocked_by' => $actor->id,
            'cod_blocked_at' => now(),
            'billing_status' => $quotation->billingStatus() === BillingStatus::Generated ? BillingStatus::Generated : BillingStatus::AwaitingRelease,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $quotation->status->value,
            'to_status' => $quotation->status->value,
            'user_id' => $actor->id,
            'remarks' => 'COD order blocked: '.$reason,
        ]);

        return $quotation->fresh();
    }

    /** @return array{ok: bool, error: ?string, quotation: Quotation} */
    public function unblockCod(Quotation $quotation, User $actor, ?string $reason = null): array
    {
        $this->assertAuthorised($actor);

        $quotation->update([
            'cod_blocked' => false,
            'cod_block_reason' => null,
            'cod_blocked_by' => null,
            'cod_blocked_at' => null,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $quotation->status->value,
            'to_status' => $quotation->status->value,
            'user_id' => $actor->id,
            'remarks' => 'COD order unblocked'.($reason ? ': '.$reason : ''),
        ]);

        if ($quotation->status === QuotationStatus::Confirmed && $quotation->billingStatus() !== BillingStatus::Generated) {
            $result = $this->billing->execute($quotation->fresh(), $actor);

            return ['ok' => $result['ok'], 'error' => $result['error'], 'quotation' => $quotation->fresh()];
        }

        return ['ok' => true, 'error' => null, 'quotation' => $quotation->fresh()];
    }

    private function assertAuthorised(User $actor): void
    {
        if ($actor->is_hq || $actor->hasAnyRole(['hq_admin', 'branch_manager', 'finance'])) {
            return;
        }

        throw new InvalidArgumentException('Only HQ Admin, Branch Manager or Finance may release or block orders.');
    }
}
