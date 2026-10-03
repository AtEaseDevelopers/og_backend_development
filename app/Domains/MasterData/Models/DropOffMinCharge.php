<?php

namespace App\Domains\MasterData\Models;

use App\Enums\DropOffType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minimum charge per destination drop-off type (section B). The exact formula is still
 * pending O&G confirmation; this table lets Admin maintain the values in the meantime.
 */
class DropOffMinCharge extends Model
{
    protected $fillable = ['branch_id', 'drop_off_type', 'minimum_charge', 'is_active', 'remarks'];

    protected function casts(): array
    {
        return [
            'drop_off_type' => DropOffType::class,
            'minimum_charge' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Active minimum for a drop-off type: branch-specific first, then global. */
    public static function minimumFor(DropOffType|string|null $type, ?int $branchId): ?float
    {
        $value = $type instanceof DropOffType ? $type->value : $type;

        if (! $value) {
            return null;
        }

        $row = static::query()
            ->where('drop_off_type', $value)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id is null')
            ->first();

        return $row ? (float) $row->minimum_charge : null;
    }
}
