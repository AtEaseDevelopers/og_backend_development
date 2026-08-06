<?php

namespace App\Domains\Delivery\Models;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofOfDelivery extends Model
{
    protected $table = 'proofs_of_delivery';

    protected $fillable = [
        'delivery_order_id', 'driver_id', 'recipient_name', 'signature_path',
        'photo_paths', 'pod_document_path', 'latitude', 'longitude',
        'cod_amount_collected', 'cod_payment_method', 'remarks',
        'client_uuid', 'delivered_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cod_amount_collected' => 'decimal:2',
            'delivered_at' => 'datetime',
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
}
