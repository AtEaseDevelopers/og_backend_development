<?php

namespace App\Domains\Delivery\Models;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedDelivery extends Model
{
    protected $fillable = [
        'delivery_order_id', 'driver_id', 'reason', 'remarks', 'photo_paths',
        'latitude', 'longitude', 'reassignment_option', 'replacement_do_id',
        'client_uuid', 'failed_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'failed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function replacementDeliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'replacement_do_id');
    }
}
