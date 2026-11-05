<?php

namespace App\Domains\Delivery\Models;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Section O: pickup proof of a delivery / additional task, and the assisting driver's handover proof. */
class DriverTaskProof extends Model
{
    public const TYPE_PICKUP = 'pickup';

    public const TYPE_HANDOVER = 'handover';

    protected $fillable = [
        'delivery_order_id', 'subsheet_id', 'driver_id', 'type', 'photo_paths', 'handover_to',
        'remarks', 'latitude', 'longitude', 'client_uuid', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function subsheet(): BelongsTo
    {
        return $this->belongsTo(Subsheet::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
