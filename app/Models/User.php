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

    /**
     * Roles and permissions are those of the "web" guard whichever login is in use (the customer portal has its own
     * guard, user 11 Nov 2026): without this, a role is looked up for the guard of the page being served.
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        // the Staff ID staff sign in to the admin panel with (user, 16 Nov 2026); lower case, unique
        'login_id',
        'password',
        // an encrypted copy of the password an admin set, shown on Master Data → Users (user, 16 Nov 2026)
        'password_readable',
        'is_hq',
        'is_active',
        'phone',
        'driver_id',
        'customer_id',
        'sa_location_id',
        'ordering_token',
        // the Register Link allowed for this user (a super admin sets it on Master Data -> Users, 17 Nov 2026)
        'register_link_enabled',
    ];

    /** The Register Link is allowed for this user (allowed unless a super admin disabled it). */
    public function registerLinkAllowed(): bool
    {
        return (bool) ($this->register_link_enabled ?? true);
    }

    /** The link this user gives a customer to register: a salesperson's own (they become the customer's salesperson), else the public one. */
    public function registerLink(): string
    {
        if ($this->hasRole('salesperson')) {
            $this->ensureOrderingToken();

            return (string) $this->orderingLink();
        }

        return route('portal.register');
    }

    public function saLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\MasterData\Models\SaLocation::class);
    }

    public function isSalesperson(): bool
    {
        return $this->hasRole('salesperson');
    }

    /** Issue (or return) this salesperson's unique customer ordering link token (section A). */
    public function ensureOrderingToken(): string
    {
        if (! $this->ordering_token) {
            $this->forceFill(['ordering_token' => \Illuminate\Support\Str::random(40)])->saveQuietly();
        }

        return $this->ordering_token;
    }

    public function orderingLink(): ?string
    {
        return $this->ordering_token
            ? route('portal.salesperson-link', ['token' => $this->ordering_token])
            : null;
    }

    protected $hidden = [
        'password',
        'password_readable',
        'remember_token',
    ];

    /** Logins created by staff or seeders count as verified; only portal self-registration must verify its email. */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! $user->requiresEmailVerification && ! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
        });

        // a password changed without its readable copy (a reset link, the driver page, a seeder) leaves no copy: the
        // old one would be wrong
        static::saving(function (User $user) {
            if ($user->isDirty('password') && ! $user->isDirty('password_readable') && array_key_exists('password_readable', $user->getAttributes())) {
                $user->password_readable = null;
            }
        });

        // the Staff ID is kept in lower case; a user saved without one gets a starting one from their email
        static::saving(function (User $user) {
            // asked once a request (an older migration may save a user before the column exists)
            static $hasColumn = null;
            if (! ($hasColumn ??= \Illuminate\Support\Facades\Schema::hasColumn('users', 'login_id'))) {
                $hasColumn = null;

                return;
            }

            $user->login_id = filled($user->login_id)
                ? static::normaliseLoginId((string) $user->login_id)
                : static::suggestLoginId((string) $user->email, $user->getKey());
        });
    }

    /** A Staff ID as it is stored and compared: trimmed, lower case. */
    public static function normaliseLoginId(string $loginId): string
    {
        return strtolower(trim($loginId));
    }

    /** A free Staff ID from the part of an email before the @ (a number is added when it is taken). */
    public static function suggestLoginId(string $email, int|string|null $ignoreId = null): string
    {
        $base = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '', explode('@', $email)[0]));
        $base = substr($base !== '' ? $base : 'user', 0, 50);
        $candidate = $base;

        for ($n = 2; static::query()->where('login_id', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $candidate = $base.$n;
        }

        return $candidate;
    }

    /** Set by portal self-registration before saving. */
    public bool $requiresEmailVerification = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_readable' => 'encrypted',
            'is_hq' => 'boolean',
            'is_active' => 'boolean',
            'register_link_enabled' => 'boolean',
            // the user's own page settings, e.g. master_data_hidden (the Master Data cards they hide)
            'preferences' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasAnyRole([
            'super_admin',
            'branch_admin',
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

    /** The transfer codes this staff member handles (Master Data → Transfer Codes). */
    public function transferCodes(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\MasterData\Models\TransferCode::class)->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): array | Collection
    {
        if ($this->isSuperadmin()) {
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

        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->companies()->where('companies.id', $tenant->id)->exists();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->branches()->where('branches.id', $branch->id)->exists();
    }

    public function isSuperadmin(): bool
    {
        return $this->is_hq || $this->hasRole('super_admin');
    }

    /** Master Data → Company pages (user, 28 Oct 2026): super_admin for every company, branch_admin for their companies only. */
    public function canManageStaff(): bool
    {
        return $this->isSuperadmin() || $this->isBranchAdmin();
    }

    public function isBranchAdmin(): bool
    {
        return ! $this->isSuperadmin() && $this->hasRole('branch_admin');
    }


    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(): Collection
    {
        if ($this->isSuperadmin()) {
            return Branch::query()->where('is_active', true)->orderBy('code')->get();
        }

        return $this->branches()
            ->where('branches.is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessiblePortalBranches(): Collection
    {
        $customerIds = $this->approvedCustomerIds();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Branch::query()
            ->where('is_active', true)
            ->whereHas('customers', fn ($query) => $query->whereIn('customers.id', $customerIds))
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Company>
     */
    public function accessiblePortalCompanies(Branch $branch): Collection
    {
        $customerIds = $this->approvedCustomerIds();

        return Company::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereHas('customers', fn ($query) => $query->whereIn('customers.id', $customerIds))
            ->orderBy('code')
            ->get();
    }

    public function canAccessPortalBranch(Branch $branch): bool
    {
        return $this->accessiblePortalBranches()->contains('id', $branch->id);
    }

    public function canAccessPortalCompany(Company $company): bool
    {
        return $this->accessiblePortalCompanies(
            Branch::query()->findOrFail($company->branch_id)
        )->contains('id', $company->id);
    }

    /** @return Collection<int, int> */
    public function approvedCustomerIds(): Collection
    {
        $ids = $this->customers()->wherePivot('status', 'approved')->pluck('customers.id');

        // older logins linked only through users.customer_id (no access row): allowed once the customer is approved
        if ($ids->isEmpty() && $this->customer_id
            && ! $this->customers()->where('customers.id', $this->customer_id)->exists()
            && $this->customer?->portal_approved) {
            return collect([$this->customer_id]);
        }

        return $ids;
    }

    /**
     * @return Collection<int, Company>
     */
    public function accessibleCompaniesForBranch(Branch $branch): Collection
    {
        if ($this->isSuperadmin()) {
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

    public function assignToBranch(Branch $branch, bool $isDefault = false): void
    {
        $this->branches()->syncWithoutDetaching([
            $branch->id => ['is_default' => $isDefault],
        ]);

        $company = $branch->defaultCompany();

        if ($company) {
            $this->companies()->syncWithoutDetaching([
                $company->id => ['is_default' => $isDefault],
            ]);
        }
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
