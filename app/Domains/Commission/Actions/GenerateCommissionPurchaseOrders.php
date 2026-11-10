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
                    'po_number' => $this->poNumber($batch, $slip),
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

    /**
     * The purchase order number (client's format, user 11 Nov 2026): YYMM/XX/lorry no, e.g. 2612/KL/JFR7300 — the
     * batch's month, the location code and the lorry's registration (a driver's own slip: the driver's name); "-2" when
     * the same one exists already.
     */
    private function poNumber(\App\Domains\Commission\Models\CommissionBatch $batch, \App\Domains\Commission\Models\CommissionSlip $slip): string
    {
        $month = preg_match('/^(\d{2})(\d{2})-(\d{2})$/', (string) $batch->month, $m) ? $m[2].$m[3] : now()->format('ym');
        $lorry = strtoupper(preg_replace('/\s+/', '', (string) ($slip->lorry?->registration_no ?? '')))
            ?: strtoupper((string) \Illuminate\Support\Str::slug((string) ($slip->driver?->name ?? 'DRIVER'), ''));
        $base = $month.'/'.\App\Services\DocumentNumberingService::locationCode((int) $batch->source_branch_id).'/'.$lorry;
        $number = $base;
        $n = 1;

        while (CommissionPurchaseOrder::query()->withoutGlobalScopes()->where('po_number', $number)->exists()) {
            $number = $base.'-'.(++$n);
        }

        return $number;
    }
}
