<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store of a branch (master data). On Create order, a consignor set to "Store" picks one: its address
 * becomes the pickup location and its PIC, contact number and "From" location fill the consignor fields.
 */
class Store extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'address', 'pic_name', 'pic_phone', 'location_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The price-list "From" location of orders picked up at this store. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return trim(($this->code ? $this->code.' — ' : '').$this->name);
    }
}
