<?php

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionPurchaseOrder;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateCommissionPurchaseOrders
{
    public function __construct(private DocumentNumberingService $numbering) {}

    /** @return Collection<int, CommissionPurchaseOrder> */
    public function execute(CommissionBatch $batch): Collection
    {
        if ($batch->status !== 'confirmed') {
            throw new InvalidArgumentException('Confirm the commission batch before generating PO/PI.');
        }

        $batch->loadMissing(['slips.driver', 'slips.purchaseOrder', 'sourceBranch']);

        return DB::transaction(function () use ($batch) {
            $created = collect();

            foreach ($batch->slips as $slip) {
                if ((float) $slip->final_amount <= 0) {
                    continue;
                }

                if ($slip->purchaseOrder) {
                    $created->push($slip->purchaseOrder);
                    continue;
                }

                $po = CommissionPurchaseOrder::query()->create([
                    'po_number' => $this->numbering->next($batch->sourceBranch, DocumentType::CommissionPo),
                    'pi_number' => $this->numbering->next($batch->sourceBranch, DocumentType::CommissionPi),
                    'commission_slip_id' => $slip->id,
                    'source_branch_id' => $batch->source_branch_id,
                    'driver_id' => $slip->driver_id,
                    'amount' => $slip->final_amount,
                    'status' => 'generated',
                    'autocount_sync_status' => 'not_synced',
                ]);

                $slip->update(['status' => 'po_generated']);
                $created->push($po);
            }

            $batch->update(['status' => 'po_generated']);

            return $created;
        });
    }
}
