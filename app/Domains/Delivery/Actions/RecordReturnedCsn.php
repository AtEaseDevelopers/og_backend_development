<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Domains\Delivery\Models\ReturnedCsn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordReturnedCsn
{
    public function execute(ConsignmentNote $csn, array $data, User $receiver): ReturnedCsn
    {
        if ($csn->returnedCsn()->exists()) {
            throw new InvalidArgumentException('This CSN has already been recorded as returned.');
        }

        return DB::transaction(function () use ($csn, $data, $receiver) {
            $do = $csn->deliveryOrders()->latest('id')->first();

            $returned = ReturnedCsn::query()->create([
                'consignment_note_id' => $csn->id,
                'delivery_order_id' => $data['delivery_order_id'] ?? $do?->id,
                'job_sheet_id' => $data['job_sheet_id'] ?? $do?->job_sheet_id,
                'returned_by_driver_id' => $data['returned_by_driver_id'] ?? $do?->driver_id,
                'received_by' => $receiver->id,
                'scan_method' => $data['scan_method'] ?? 'manual',
                'status' => 'received',
                'is_signed' => (bool) ($data['is_signed'] ?? true),
                'is_stamped' => (bool) ($data['is_stamped'] ?? false),
                'returned_at' => $data['returned_at'] ?? now(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $csn->update(['return_status' => 'returned']);

            MissingCsnLog::query()
                ->where('consignment_note_id', $csn->id)
                ->whereIn('status', ['pending_return', 'missing'])
                ->update([
                    'status' => 'resolved',
                    'returned_csn_id' => $returned->id,
                    'resolved_at' => now(),
                    'resolved_by' => $receiver->id,
                    'investigation_status' => 'returned',
                ]);

            // Release draft commission lines immediately; confirmed batches stay locked.
            CommissionLineItem::query()
                ->where('consignment_note_id', $csn->id)
                ->whereHas('slip.batch', fn ($q) => $q->where('status', 'draft'))
                ->get()
                ->each(function (CommissionLineItem $line) {
                    $line->update([
                        'is_eligible' => true,
                        'is_carry_forward' => false,
                    ]);
                });

            return $returned->load(['consignmentNote', 'returnedByDriver', 'receivedBy']);
        });
    }

    public function executeByQrToken(string $token, array $data, User $receiver): ReturnedCsn
    {
        $csn = ConsignmentNote::query()->where('qr_token', $token)->first();

        if (! $csn) {
            throw new InvalidArgumentException('CSN QR token not found.');
        }

        $data['scan_method'] = 'qr';

        return $this->execute($csn, $data, $receiver);
    }
}
