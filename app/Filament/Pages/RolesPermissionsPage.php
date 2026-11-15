<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Master Data → System settings → Roles & Permission (user, 13 Nov 2026): every action of the system down the side
 * (App\Support\Permissions::catalog(), by module, each module split into View / Create / Edit / Delete) and a tick box per role; ticking gives the action to everyone with
 * that role, unticking takes it away. A super admin may always do everything (its column is ticked and fixed), so an
 * admin cannot lock themselves out. One user's own exceptions are set on the user (Users → Permissions).
 */
class RolesPermissionsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Roles & Permission';

    protected static ?string $slug = 'roles-permissions';

    protected static string $view = 'filament.pages.roles-permissions';

    /** @var array<string, array<string, bool>> action key (dots as __) => role => ticked */
    public array $matrix = [];

    /** Open to the roles ticked for "settings.roles" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canAccess(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    public function getTitle(): string
    {
        return 'Roles & Permission';
    }

    public function mount(): void
    {
        $this->loadMatrix();
    }

    /** The roles with a column: the staff roles, without the super admin (shown ticked and fixed). @return list<string> */
    public function roleNames(): array
    {
        return array_values(array_diff(Permissions::STAFF, ['super_admin']));
    }

    public static function key(string $permission): string
    {
        return str_replace('.', '__', $permission);
    }

    public function roleLabel(string $role): string
    {
        return Str::headline($role);
    }

    private function loadMatrix(): void
    {
        $held = Role::query()->where('guard_name', 'web')->whereIn('name', $this->roleNames())->with('permissions:id,name')->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->flip()]);

        $this->matrix = [];
        foreach (array_keys(Permissions::catalog()) as $permission) {
            foreach ($this->roleNames() as $role) {
                $this->matrix[static::key($permission)][$role] = (bool) ($held[$role] ?? collect())->has($permission);
            }
        }
    }

    /** @return array<string, int> staff of each role (the portal's customers are not staff) */
    public function staffCounts(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->selectRaw('roles.name as role, count(*) as staff')
            ->groupBy('roles.name')
            ->pluck('staff', 'role')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** Users with their own exceptions (allow / deny), so nothing is hidden from this page. */
    public function overrideCount(): int
    {
        return DB::table('user_permission_overrides')->distinct()->count('user_id');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        foreach ($this->roleNames() as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $ticked = collect(array_keys(Permissions::catalog()))
                ->filter(fn (string $permission) => (bool) ($this->matrix[static::key($permission)][$roleName] ?? false))
                ->values()
                ->all();
            $role->syncPermissions($ticked);
        }

        Permissions::flush();
        $this->loadMatrix();

        Notification::make()->title('Permissions saved')->body('They apply from the next page each user opens.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save permissions')->action('save'),
        ];
    }
}
