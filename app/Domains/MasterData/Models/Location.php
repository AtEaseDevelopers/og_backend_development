<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    // quote_column (user, 29 Oct 2026): destinations with the same label print as one column on the quotation PDF
    protected $fillable = ['code', 'name', 'quote_column', 'type', 'postcode', 'state', 'city', 'is_active'];

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
