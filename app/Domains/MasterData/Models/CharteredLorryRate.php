<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharteredLorryRate extends Model
{
    protected $fillable = ['chartered_lorry_id', 'location_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
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
