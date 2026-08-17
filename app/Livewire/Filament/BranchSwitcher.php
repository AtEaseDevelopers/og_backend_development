<?php

namespace App\Livewire\Filament;

use App\Domains\MasterData\Models\Branch;
use App\Support\CurrentBranch;
use App\Support\SwitchBranch;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class BranchSwitcher extends Component
{
    public function switchBranch(int $branchId): void
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        $branch = $user->accessibleBranches()->firstWhere('id', $branchId);

        abort_unless($branch instanceof Branch, 403);

        $this->redirect(SwitchBranch::enter($user, $branch));
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranchesProperty(): Collection
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();

        return $user->accessibleBranches();
    }

    public function render(): View
    {
        if (! Filament::auth()->check() || ! Filament::getTenant()) {
            return view('livewire.filament.branch-switcher-empty');
        }

        $currentBranch = CurrentBranch::get();
        $branches = $this->branches;

        return view('livewire.filament.branch-switcher', [
            'currentBranch' => $currentBranch,
            'branches' => $branches,
            'canSwitch' => $branches->count() > 1,
        ]);
    }
}
