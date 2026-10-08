<?php

namespace App\Http\Responses\Auth;

use App\Support\DefaultBranch;
use App\Support\SelectedBranch;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        SelectedBranch::clear();

        // Land straight in the home branch (KL). A remembered deep link is honoured, but never the
        // panel root or the old branch chooser (both would only bounce to the branch dashboard).
        $user = Filament::auth()->user();
        $home = ($user instanceof \App\Models\User ? DefaultBranch::url($user) : null) ?? DefaultBranch::homeUrl();
        $intended = session()->pull('url.intended');
        $path = $intended ? rtrim((string) parse_url($intended, PHP_URL_PATH), '/') : '';

        if ($intended && $path !== '' && $path !== '/admin' && ! str_ends_with($path, '/select-branch')) {
            return redirect()->to($intended);
        }

        return redirect()->to($home);
    }
}
