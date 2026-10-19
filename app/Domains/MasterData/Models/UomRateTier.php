<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UomRateTier extends Model
{
    /**
     * One quantity range of a product's price to a destination. customer_code blank = the default price, else the
     * special price of the customer with that AutoCount debtor code; the minimum charges come from the price list row.
     */
    protected $fillable = [
        'uom_id', 'location_id', 'customer_code', 'branch_id', 'drop_off_type', 'pickup_location',
        'min_qty', 'max_qty', 'price', 'pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge',
        'effective_from', 'expires_on', 'source',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:2',
            'max_qty' => 'decimal:2',
            'price' => 'decimal:2',
            'pickup_min_charge' => 'decimal:2',
            'construction_min_charge' => 'decimal:2',
            'drop_off_min_charge' => 'decimal:2',
            'effective_from' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** "1", "2–9", "20+" */
    public function rangeLabel(): string
    {
        $min = number_format((float) $this->min_qty, 0);

        if ($this->max_qty === null) {
            return $min.'+';
        }

        return (float) $this->max_qty === (float) $this->min_qty ? $min : $min.'–'.number_format((float) $this->max_qty, 0);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
