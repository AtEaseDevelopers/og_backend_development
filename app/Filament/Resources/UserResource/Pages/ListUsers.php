<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

class ListUsers extends ListRecords
{
    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    use HasExcelColumnFilters;

    protected static string $resource = UserResource::class;

    /** The role picked beside the search box ('' = all roles); kept in the address so the list can be shared or reloaded. */
    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    public function mount(): void
    {
        parent::mount();

        // the Role dropdown sits in the search row itself (user, 13 Nov 2026: no separate "Filters" card for one filter)
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn () => view('filament.tables.user-role-filter', ['roles' => $this->roleOptions()]),
            scopes: static::class,
        );
    }

    /** @return array<string, string> role name => label; the super admin role only for a super admin, never the portal's customers */
    public function roleOptions(): array
    {
        return Role::query()
            ->where('name', '!=', 'customer')
            ->when(! auth()->user()?->isSuperadmin(), fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => Str::headline($name)])
            ->all();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery() ?? static::getResource()::getEloquentQuery();

        return $query->when(
            $this->roleFilter !== '' && array_key_exists($this->roleFilter, $this->roleOptions()),
            fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('roles.name', $this->roleFilter)),
        );
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
