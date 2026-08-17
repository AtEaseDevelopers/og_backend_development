<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UomRateTier extends Model
{
    protected $fillable = ['uom_id', 'location_id', 'min_qty', 'max_qty', 'price'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:2',
            'max_qty' => 'decimal:2',
            'price' => 'decimal:2',
        ];
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
