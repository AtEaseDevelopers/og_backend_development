<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Enums\CsnStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FlagMissingCsns
{
    /**
     * Mark delivered CSNs still not returned after the configured grace period.
     *
     * @return Collection<int, MissingCsnLog>
     */
    public function execute(?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? now())->copy();
        $days = (int) config('og.missing_csn_days', 7);
        $cutoff = $asOf->copy()->subDays($days);

        $candidates = ConsignmentNote::query()
            ->with(['deliveryOrders' => fn ($q) => $q->latest('id')])
            ->where('status', CsnStatus::Delivered)
            ->whereIn('return_status', ['pending_return', 'not_required'])
            ->whereDoesntHave('returnedCsn')
            ->whereHas('deliveryOrders', fn ($q) => $q->where('delivered_at', '<=', $cutoff))
            ->get();

        $logs = collect();

        foreach ($candidates as $csn) {
            $do = $csn->deliveryOrders->first();

            $log = DB::transaction(function () use ($csn, $do, $asOf) {
                $csn->update(['return_status' => 'missing']);

                $existing = MissingCsnLog::query()
                    ->where('consignment_note_id', $csn->id)
                    ->whereIn('status', ['pending_return', 'missing'])
                    ->latest('id')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'company_id' => $csn->company_id ?? $do?->company_id,
                        'status' => 'missing',
                        'source_branch_id' => $csn->source_branch_id,
                        'delivery_order_id' => $do?->id,
                        'marked_missing_at' => $asOf,
                        'investigation_status' => 'open',
                        'follow_up_remarks' => 'Auto-flagged after missing CSN grace period',
                    ]);

                    return $existing;
                }

                return MissingCsnLog::query()->create([
                    'consignment_note_id' => $csn->id,
                    'company_id' => $csn->company_id ?? $do?->company_id,
                    'source_branch_id' => $csn->source_branch_id,
                    'delivery_order_id' => $do?->id,
                    'status' => 'missing',
                    'marked_missing_at' => $asOf,
                    'investigation_status' => 'open',
                    'follow_up_remarks' => 'Auto-flagged after missing CSN grace period',
                ]);
            });

            $logs->push($log->fresh());
        }

        return $logs;
    }

    public function ensurePendingReturn(ConsignmentNote $csn): MissingCsnLog
    {
        $do = $csn->deliveryOrders()->latest('id')->first();

        if (in_array($csn->return_status, [null, '', 'not_required'], true)) {
            $csn->update(['return_status' => 'pending_return']);
        }

        return MissingCsnLog::query()->firstOrCreate(
            [
                'consignment_note_id' => $csn->id,
                'status' => 'pending_return',
            ],
            [
                'company_id' => $csn->company_id ?? $do?->company_id,
                'source_branch_id' => $csn->source_branch_id,
                'delivery_order_id' => $do?->id,
                'marked_missing_at' => null,
                'investigation_status' => 'awaiting_return',
            ]
        );
    }
}
