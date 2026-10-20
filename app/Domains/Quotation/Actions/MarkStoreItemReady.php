<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Ready for CSN" (user, 21 Oct 2026): a Store order's goods are at the store. It was paid or released and billed
 * but kept at Payment verification / Pending item; now its CSN is created and the order moves to CSN created.
 */
class MarkStoreItemReady
{
    public function __construct(private GenerateOrderBilling $billing) {}

    /** @return Collection the CSN(s) created */
    public function execute(Quotation $quotation, User $actor): Collection
    {
        return DB::transaction(function () use ($quotation, $actor) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if (! $quotation->awaitsItem()) {
                throw new InvalidArgumentException('Order '.$quotation->number.' is not waiting for its goods at the store.');
            }

            if (! $quotation->isBilledAwaitingItem()) {
                throw new InvalidArgumentException('Order '.$quotation->number.' is not paid / released yet: record the payment first.');
            }

            $quotation->update(['item_received_at' => now(), 'item_received_by' => $actor->id]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $quotation->status->value,
                'to_status' => $quotation->status->value,
                'user_id' => $actor->id,
                'remarks' => 'Goods at the store · ready for CSN',
            ]);

            return $this->billing->createCsns($quotation->fresh(), $actor);
        });
    }
}
