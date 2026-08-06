<?php

namespace App\Domains\Commission\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLineItem extends Model
{
    protected $fillable = [
        'commission_slip_id', 'delivery_order_id', 'consignment_note_id',
        'driver_id', 'lorry_id', 'amount', 'split_percent', 'line_type',
        'is_eligible', 'is_hidden', 'hidden_reason', 'is_carry_forward', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'split_percent' => 'decimal:2',
            'is_eligible' => 'boolean',
            'is_hidden' => 'boolean',
            'is_carry_forward' => 'boolean',
        ];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(CommissionSlip::class, 'commission_slip_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function lorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class);
    }
}
