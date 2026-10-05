<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use Throwable;

/**
 * Non-cash orders (credit term / COD) skip the payment summary: once the order is confirmed
 * (and the credit check or credit approval has passed) it proceeds straight to the invoice
 * and CSN. Term orders are released automatically at that point; COD orders are billed unless
 * Admin has blocked them.
 */
class ProceedNonCashOrderToCsn
{
    public function __construct(private GenerateOrderBilling $billing) {}

    /** @return array{ok: bool, error: ?string}|null null when the order is a cash order or not confirmed */
    public function execute(Quotation $quotation, ?User $actor = null): ?array
    {
        $quotation->refresh();

        if ($quotation->status !== QuotationStatus::Confirmed || $quotation->billingStatus() === BillingStatus::Generated) {
            return null;
        }

        $type = $quotation->orderType();

        if (! in_array($type, [OrderType::Term, OrderType::Cod], true)) {
            return null;
        }

        if ($type === OrderType::Cod && $quotation->cod_blocked) {
            return ['ok' => false, 'error' => 'COD order is blocked by Admin.'];
        }

        if ($type === OrderType::Term && ! $quotation->isReleased()) {
            $quotation->update([
                'released_at' => now(),
                'released_by' => $actor?->id,
                'release_reason' => 'Credit term order · released automatically on confirmation',
                'release_outstanding' => $quotation->outstandingAmount(),
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $quotation->status->value,
                'to_status' => $quotation->status->value,
                'user_id' => $actor?->id,
                'remarks' => 'Credit term order · no payment collection · released for invoice and CSN',
            ]);
        }

        try {
            $result = $this->billing->execute($quotation->fresh(), $actor);

            return ['ok' => (bool) $result['ok'], 'error' => $result['error'] ?? null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
