<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    public const MEASUREMENT = 'measurement';

    public const ITEM = 'item';

    /** A chartered lorry trip (user, 21 Oct 2026: lorries are products too, priced per destination like the others). */
    public const LORRY = 'lorry';

    public const CATEGORIES = [self::MEASUREMENT => 'Measurement', self::ITEM => 'Item', self::LORRY => 'Chartered Lorry'];

    /** A product of the client's price list is one size / weight band of an item (category measurement | item | lorry). */
    protected $fillable = [
        'branch_id', 'code', 'name', 'is_active', 'category', 'sub_category', 'base_item',
        'min_lwh', 'max_lwh', 'min_length', 'max_length', 'max_width', 'max_height', 'min_weight', 'max_weight',
        // priced per kg (client, 1 Nov 2026): the range price is a rate per kg, the unit price = rate × weight
        'priced_per_kg',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priced_per_kg' => 'boolean',
            'min_lwh' => 'decimal:2',
            'max_lwh' => 'decimal:2',
            'min_length' => 'decimal:2',
            'max_length' => 'decimal:2',
            'max_width' => 'decimal:2',
            'max_height' => 'decimal:2',
            'min_weight' => 'decimal:2',
            'max_weight' => 'decimal:2',
        ];
    }

    /**
     * Per company (user, 28 Oct 2026): in the panel every query sees the current company's rows only, and a new row
     * belongs to the current company. Outside a company (console, migrations) nothing is filtered.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            if ($branchId = \App\Support\CurrentCompany::branchId()) {
                $query->where($query->qualifyColumn('branch_id'), $branchId);
            }
        });

        static::creating(function (self $row): void {
            // branch_id exists only from 2026_10_28_120000; price-list import runs before that on migrate
            if (! \Illuminate\Support\Facades\Schema::hasColumn($row->getTable(), 'branch_id')) {
                return;
            }

            $row->branch_id ??= \App\Support\CurrentCompany::branchId();
        });
    }

    public function rateTiers(): HasMany
    {
        return $this->hasMany(UomRateTier::class);
    }
}
