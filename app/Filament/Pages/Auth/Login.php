<?php

namespace App\Filament\Pages\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * The admin login. Staff sign in with their Staff ID, not their email (user, 16 Nov 2026): the Staff ID is set on
 * Master Data → Users (users.login_id, kept in lower case, so it is not case-sensitive here). The customer portal
 * and the driver app keep their own logins.
 */
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

    /** The first field of the form: the Staff ID in place of the email address. */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login_id')
            ->label('Staff ID')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /** @return array<string, mixed> */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'login_id' => \App\Models\User::normaliseLoginId($data['login_id'] ?? ''),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login_id' => 'The Staff ID or password is not correct.',
        ]);
    }
}
