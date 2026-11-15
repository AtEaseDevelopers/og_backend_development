<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\SaLocation;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

/**
 * Master Data → System settings → Organisation Chart (user, 16 Nov 2026): every company the viewer may see, the number
 * of its branches, and under each branch its users with their role, contact number and email (a user without a branch
 * is listed as company staff). Filtered by company, role and user (name, Staff ID, email or phone) in one row.
 * Read-only: users, their role and branch are changed under Users.
 */
class OrganisationChartPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Organisation Chart';

    protected static ?string $slug = 'organisation-chart';

    protected static string $view = 'filament.pages.organisation-chart';

    /** The order the roles are listed in (the rest follow by name). */
    private const ROLE_ORDER = ['super_admin', 'branch_admin', 'branch_manager', 'finance', 'salesperson', 'counter', 'dispatcher', 'storekeeper', 'driver'];

    #[Url(as: 'company', except: '')]
    public string $companyFilter = '';

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    #[Url(as: 'user', except: '')]
    public string $userFilter = '';

    /** Open to the roles ticked for "users.view" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canAccess(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    public function getTitle(): string
    {
        return 'Organisation Chart';
    }

    public function clearFilters(): void
    {
        $this->reset('companyFilter', 'roleFilter', 'userFilter');
    }

    /** The companies the viewer may see: all for a super admin, their own otherwise. */
    private function companies(): Collection
    {
        $viewer = auth()->user();

        return Company::query()
            ->where('is_active', true)
            ->when(! $viewer?->isSuperadmin(), fn ($q) => $q->whereIn('id', $viewer?->companies()->pluck('companies.id') ?? []))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /** @return array<string, string> id => "KL — name" */
    public function companyOptions(): array
    {
        return $this->companies()->mapWithKeys(fn (Company $c) => [(string) $c->id => trim(($c->code ? $c->code.' — ' : '').$c->name)])->all();
    }

    /** @return array<string, string> role name => label (the staff roles and drivers; never the portal's customers) */
    public function roleOptions(): array
    {
        $rank = array_flip(self::ROLE_ORDER);

        return Role::query()->where('name', '!=', 'customer')->pluck('name')
            ->sortBy(fn (string $name) => sprintf('%02d-%s', $rank[$name] ?? 99, $name))
            ->mapWithKeys(fn (string $name) => [$name => Str::headline($name)])
            ->all();
    }

    /**
     * The chart: per company its branches, per branch its users.
     *
     * @return array{companies: list<array{id: int, name: string, code: ?string, branch_count: int, staff_count: int, groups: list<array{name: string, code: ?string, is_branch: bool, users: list<array{name: string, login_id: ?string, role: string, phone: ?string, email: ?string}>}>}>, total: int, filtered: bool}
     */
    public function chart(): array
    {
        $companies = $this->companies()->when($this->companyFilter !== '', fn (Collection $c) => $c->where('id', (int) $this->companyFilter))->values();
        $companyIds = $companies->pluck('id')->all();
        $search = trim($this->userFilter);
        $filtered = $this->roleFilter !== '' || $search !== '';

        $users = User::query()
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'customer'))
            ->when($this->roleFilter !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->roleFilter)))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('login_id', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')))
            ->with(['roles:id,name', 'companies:id'])
            ->orderBy('name')
            ->get(['id', 'name', 'login_id', 'email', 'phone', 'sa_location_id', 'is_hq']);

        $branches = SaLocation::query()->whereIn('company_id', $companyIds)->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'name', 'code']);
        $rank = array_flip(self::ROLE_ORDER);
        $row = function (User $user) use ($rank): array {
            $role = $user->roles->sortBy(fn ($r) => $rank[$r->name] ?? 99)->first()?->name;

            return [
                'name' => (string) $user->name,
                'login_id' => $user->login_id,
                'role' => $role ? Str::headline($role) : 'No role',
                'rank' => $rank[$role] ?? 99,
                'phone' => filled($user->phone) ? (string) $user->phone : null,
                'email' => filled($user->email) ? (string) $user->email : null,
            ];
        };
        $sorted = fn (Collection $list): array => $list->map($row)->sortBy([['rank', 'asc'], ['name', 'asc']])->values()->all();

        // a user of several companies is counted once in the total
        $seen = [];
        $out = [];
        foreach ($companies as $company) {
            // a user belongs to the companies they are given; HQ belongs to every company
            $staff = $users->filter(fn (User $u) => $u->is_hq || $u->companies->contains('id', $company->id));
            $ownBranches = $branches->where('company_id', $company->id);
            $branchIds = $ownBranches->pluck('id')->all();
            $groups = [];

            $companyLevel = $staff->filter(fn (User $u) => ! in_array((int) $u->sa_location_id, $branchIds, true));
            if ($companyLevel->isNotEmpty() || ! $filtered) {
                $groups[] = ['name' => 'Company staff', 'code' => 'not under a branch', 'is_branch' => false, 'users' => $sorted($companyLevel)];
            }
            foreach ($ownBranches as $branch) {
                $members = $staff->where('sa_location_id', $branch->id);
                // with a role or user filter on, a branch without a match is left out
                if ($members->isEmpty() && $filtered) {
                    continue;
                }
                $groups[] = ['name' => (string) $branch->name, 'code' => $branch->code, 'is_branch' => true, 'users' => $sorted($members)];
            }

            if ($filtered && $staff->isEmpty()) {
                continue;
            }

            $seen += $staff->pluck('id', 'id')->all();
            $out[] = [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'code' => $company->code,
                'branch_count' => $ownBranches->count(),
                'staff_count' => $staff->count(),
                'groups' => $groups,
            ];
        }

        return ['companies' => $out, 'total' => count($seen), 'filtered' => $filtered || $this->companyFilter !== ''];
    }
}
