<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A branch of the company (Master Data → Branches; formerly "SA location"). Each salesperson belongs to one branch,
 * each branch carries the CSN location code (KL, SA, …), and each branch is a store a consignor can bring the goods
 * to (user, 26 Oct 2026): its store row (Store, stores.sa_location_id) is kept in step here.
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

    protected static function booted(): void
    {
        static::saved(fn (SaLocation $branch) => $branch->syncStore());
    }

    /**
     * A new branch gets a first store with its code, name and address (user, 28 Oct 2026: stores are managed in
     * Master Data → Stores, several per branch, so an existing store is never overwritten here).
     */
    public function syncStore(): Store
    {
        return Store::query()->firstOrCreate(['sa_location_id' => $this->id], [
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'is_active' => (bool) $this->is_active,
        ]);
    }

    public function stores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Store::class, 'sa_location_id');
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Store::class, 'sa_location_id')->oldestOfMany();
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
