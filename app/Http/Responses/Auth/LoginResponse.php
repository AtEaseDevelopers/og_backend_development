<?php

namespace App\Http\Responses\Auth;

use App\Support\SelectedBranch;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        SelectedBranch::clear();

        return redirect()->intended(route('filament.admin.select-branch'));
    }
}
