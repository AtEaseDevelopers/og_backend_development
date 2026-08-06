<?php

namespace App\Models;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_hq',
        'is_active',
        'phone',
        'driver_id',
        'customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_hq' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasAnyRole([
            'hq_admin',
            'branch_manager',
            'counter',
            'finance',
            'dispatcher',
            'salesperson',
            'storekeeper',
        ]) || $this->is_hq;
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): array | Collection
    {
        if ($this->is_hq || $this->hasRole('hq_admin')) {
            return Company::query()->where('is_active', true)->orderBy('code')->get();
        }

        return $this->companies()
            ->where('companies.is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Company) {
            return false;
        }

        if ($this->is_hq || $this->hasRole('hq_admin')) {
            return true;
        }

        return $this->companies()->where('companies.id', $tenant->id)->exists();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($this->is_hq || $this->hasRole('hq_admin')) {
            return true;
        }

        return $this->branches()->where('branches.id', $branch->id)->exists();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(): Collection
    {
        if ($this->is_hq || $this->hasRole('hq_admin')) {
            return Branch::query()->where('is_active', true)->orderBy('code')->get();
        }

        return $this->branches()
            ->where('branches.is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Company>
     */
    public function accessibleCompaniesForBranch(Branch $branch): Collection
    {
        if ($this->is_hq || $this->hasRole('hq_admin')) {
            return Company::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return $this->companies()
            ->where('companies.branch_id', $branch->id)
            ->where('companies.is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)
            ->withPivot('status')
            ->withTimestamps();
    }

    public function defaultBranch(): ?Branch
    {
        return $this->branches()->wherePivot('is_default', true)->first()
            ?? $this->branches()->first();
    }

    public function accessibleBranchIds(): array
    {
        return $this->accessibleBranches()->pluck('id')->all();
    }
}
