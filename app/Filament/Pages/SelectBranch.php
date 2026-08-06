<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Support\SelectedBranch;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Panel;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class SelectBranch extends SimplePage
{
    protected static string $view = 'filament.pages.select-branch';

    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'select-branch';

    public function mount(): void
    {
        abort_unless(Filament::auth()->check(), 403);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Choose branch';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Choose a branch';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Pick a branch first. Next you will select or register a company under that branch.';
    }

    public function getMaxWidth(): MaxWidth | string | null
    {
        return MaxWidth::FourExtraLarge;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranches(): Collection
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        return $user->accessibleBranches();
    }

    public function enterBranch(int $branchId): void
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $branch = $user->accessibleBranches()->firstWhere('id', $branchId);

        abort_unless($branch instanceof Branch && $user->canAccessBranch($branch), 403);

        SelectedBranch::set($branch);

        $this->redirect(route('filament.admin.select-company'));
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return route('filament.admin.select-branch', $parameters, $isAbsolute);
    }

    public static function registerRoutes(Panel $panel): void
    {
        // Registered via authenticatedRoutes in AdminPanelProvider (outside tenant scope).
    }
}
