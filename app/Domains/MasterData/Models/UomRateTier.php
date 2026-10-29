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
        'min_qty', 'max_qty', 'price', 'fixed_charge', 'pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge',
        'effective_from', 'expires_on', 'source',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:2',
            'max_qty' => 'decimal:2',
            'price' => 'decimal:2',
            'fixed_charge' => 'decimal:2',
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

    /**
     * The unit price for a quantity (user, 29 Oct 2026): the range's price, plus its fixed charge spread over the
     * quantity when the range is priced as "(250+4.0X)" (RM 250 + RM 4.00 per unit).
     */
    public function unitPriceFor(float $quantity): float
    {
        return round((float) $this->price + (float) ($this->fixed_charge ?? 0) / max($quantity, 0.01), 2);
    }

    /** "RM 4.00 each", or "RM 250.00 + RM 4.00 each" with a fixed charge. */
    public function priceText(): string
    {
        $each = 'RM '.number_format((float) $this->price, 2).' each';

        return $this->fixed_charge !== null && (float) $this->fixed_charge > 0 ? 'RM '.number_format((float) $this->fixed_charge, 2).' + '.$each : $each;
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
