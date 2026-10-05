<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Filament\Facades\Filament;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Picks the branch a user lands in after login so the "Choose a branch" screen is skipped.
 * KL (HQ) wins when the user can access it; otherwise the user's default branch, then the first one.
 * The chooser stays reachable from the user menu ("Change branch").
 */
class DefaultBranch
{
    public const HOME_BRANCH_CODE = 'KL';

    public static function resolve(User $user): ?Branch
    {
        $branches = $user->accessibleBranches();

        if ($branches->isEmpty()) {
            return null;
        }

        $home = $branches->first(fn (Branch $branch) => strtoupper((string) $branch->code) === self::HOME_BRANCH_CODE);

        if ($home) {
            return $home;
        }

        $default = $user->branches()->wherePivot('is_default', true)->first();

        if ($default && $branches->contains('id', $default->id)) {
            return $default;
        }

        return $branches->first();
    }

    /** URL of the dashboard inside the resolved branch, or null when none can be entered. */
    public static function url(User $user): ?string
    {
        $branch = self::resolve($user);

        if (! $branch) {
            return null;
        }

        try {
            return SwitchBranch::enter($user, $branch);
        } catch (HttpException) {
            return null;
        }
    }

    /** Where the panel's home link / post-login redirect should go for the current user. */
    public static function homeUrl(): string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return route('filament.admin.select-branch');
        }

        $selected = SelectedBranch::get();

        if ($selected && $user->canAccessBranch($selected)) {
            try {
                return SwitchBranch::enter($user, $selected);
            } catch (HttpException) {
                // fall through to the default branch
            }
        }

        return self::url($user) ?? route('filament.admin.select-branch');
    }
}
