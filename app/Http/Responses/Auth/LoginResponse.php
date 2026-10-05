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

        // Land straight in the home branch (KL) instead of the branch chooser.
        $user = Filament::auth()->user();
        $home = $user instanceof \App\Models\User ? DefaultBranch::url($user) : null;

        return redirect()->intended($home ?? route('filament.admin.select-branch'));
    }
}
