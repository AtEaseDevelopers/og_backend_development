<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    // deleted on Master Data -> Area (user, 13 Nov 2026): kept in the table, so the orders and CSNs that used the area
    // still show its name (their relations read it withTrashed); it is no longer offered anywhere
    use SoftDeletes;

    /**
     * The area of this name, a deleted one brought back (the name is unique in the table, so a deleted area cannot be
     * created a second time): used wherever areas are created by name.
     */
    public static function restoreOrCreate(string $name, array $values = []): static
    {
        $location = static::withTrashed()->where('name', $name)->first();

        if ($location) {
            if ($location->trashed()) {
                $location->restore();
            }
            $location->fill($values)->save();

            return $location;
        }

        return static::query()->create(['name' => $name] + $values);
    }

    /** Price rows of any kind: an area with none can be deleted. */
    public function hasPriceRows(): bool
    {
        return $this->uomRateTiers()->exists() || $this->itemRates()->exists() || $this->charteredLorryRates()->exists();
    }

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
