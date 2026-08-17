<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['code', 'name', 'type', 'postcode', 'state', 'city', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function itemRates(): HasMany
    {
        return $this->hasMany(ItemRate::class);
    }

    public function uomRateTiers(): HasMany
    {
        return $this->hasMany(UomRateTier::class);
    }

    public function charteredLorryRates(): HasMany
    {
        return $this->hasMany(CharteredLorryRate::class);
    }
}
