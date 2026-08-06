<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPricing extends Model
{
    protected $table = 'customer_pricing';

    protected $fillable = [
        'customer_id', 'item_name', 'uom', 'route', 'destination',
        'postcode', 'state', 'base_price', 'unit_rate', 'min_charge', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'min_charge' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
