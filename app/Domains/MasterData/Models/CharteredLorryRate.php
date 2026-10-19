<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharteredLorryRate extends Model
{
    /** customer_code blank = the default price, else the special price of the customer with that debtor code. */
    protected $fillable = [
        'chartered_lorry_id', 'location_id', 'customer_code', 'branch_id', 'drop_off_type', 'pickup_location',
        'price', 'effective_from', 'expires_on', 'source',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'effective_from' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function charteredLorry(): BelongsTo
    {
        return $this->belongsTo(CharteredLorry::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
