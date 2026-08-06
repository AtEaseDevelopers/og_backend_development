<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Enums\DocumentType;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateBreakBulk
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(DeliveryOrder $do, array $data, ?Driver $requestingDriver = null, ?User $actor = null): BreakBulk
    {
        $do->loadMissing(['jobSheet.operatingBranch', 'sourceBranch', 'consignmentNote']);

        $active = BreakBulk::query()
            ->where('delivery_order_id', $do->id)
            ->where('status', 'active')
            ->exists();

        if ($active) {
            throw new InvalidArgumentException('An active Break-Bulk already exists. Revoke it before creating another.');
        }

        if (blank($data['reason'] ?? null)) {
            throw new InvalidArgumentException('Break-Bulk reason is required.');
        }

        return DB::transaction(function () use ($do, $data, $requestingDriver, $actor) {
            $branch = $do->jobSheet?->operatingBranch ?? $do->sourceBranch;

            return BreakBulk::query()->create([
                'number' => $this->numbering->next($branch, DocumentType::BreakBulk),
                'delivery_order_id' => $do->id,
                'job_sheet_id' => $do->job_sheet_id,
                'consignment_note_id' => $do->consignment_note_id,
                'original_driver_id' => $do->driver_id,
                'original_lorry_id' => $do->lorry_id,
                'requested_by_driver_id' => $requestingDriver?->id,
                'created_by' => $actor?->id,
                'location' => $data['location'] ?? null,
                'reason' => $data['reason'],
                'handover_status' => 'pending',
                'status' => 'active',
                'photo_paths' => $data['photo_paths'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);
        });
    }
}
