<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sales Area (SA) location. Each salesperson belongs to one SA location, and each
 * SA location carries the CSN number prefix used for its orders (section A / H).
 */
class SaLocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'csn_prefix', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salespersons(): HasMany
    {
        return $this->hasMany(User::class, 'sa_location_id');
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name.' ('.$this->csn_prefix.')';
    }
}
