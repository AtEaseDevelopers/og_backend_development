<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(\App\Support\DefaultBranch::homeUrl());

            return;
        }

        $this->form->fill();
    }
}
