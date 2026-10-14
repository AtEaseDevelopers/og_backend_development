<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Throwable;

class SwitchBranch
{
    public static function enter(User $user, Branch $branch): string
    {
        abort_unless($user->canAccessBranch($branch), 403);

        SelectedBranch::set($branch);

        $company = $branch->defaultCompany();
        abort_unless($company instanceof Company, 404);
        abort_unless($user->canAccessTenant($company), 403);

        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

        return $panel->getUrl($company);
    }

    /**
     * Switch branch and stay on the page being viewed: the same page of the new branch (its filters kept). A page of
     * one record (CSN 45, an order, a job sheet…) belongs to the old branch, so the switch opens that list instead;
     * a page the new branch does not have falls back to its dashboard.
     */
    public static function enterKeepingPage(User $user, Branch $branch, ?string $currentUrl): string
    {
        $home = static::enter($user, $branch);

        if (blank($currentUrl)) {
            return $home;
        }

        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');
        $path = (string) parse_url($currentUrl, PHP_URL_PATH);
        $query = (string) parse_url($currentUrl, PHP_URL_QUERY);
        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));
        $panelIndex = array_search(trim((string) $panel->getPath(), '/'), $segments, true);

        // /{panel}/{tenant}/{page…}: the page part after the old branch's tenant slug
        if ($panelIndex === false || ! isset($segments[$panelIndex + 1])) {
            return $home;
        }

        $page = array_slice($segments, $panelIndex + 2);

        // stop before the first record id (a record of the old branch)
        $recordAt = collect($page)->search(fn (string $segment) => ctype_digit($segment));
        $keepQuery = $recordAt === false;

        if ($recordAt !== false) {
            $page = array_slice($page, 0, $recordAt);
        }

        while ($page !== []) {
            $candidate = rtrim($home, '/').'/'.implode('/', $page);

            if (static::isPage($candidate)) {
                return $candidate.($keepQuery && $query !== '' ? '?'.$query : '');
            }

            array_pop($page);
            $keepQuery = false;
        }

        return $home;
    }

    private static function isPage(string $url): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($url, 'GET'));

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
