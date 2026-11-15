<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\Permissions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use \App\Filament\Concerns\FormActionsInHeader;

    use \App\Filament\Concerns\MasterDataBreadcrumbs;

    protected static string $resource = UserResource::class;

    /** "Permissions (this user only)": each box is what the user may do now (their role, then their own exceptions). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getRecord();
        $overrides = Permissions::overridesFor($user);
        $boxes = [];

        foreach (Permissions::fromRoles($user) as $permission => $fromRole) {
            $boxes[str_replace('.', '__', $permission)] = isset($overrides[$permission]) ? $overrides[$permission] === 'allow' : $fromRole;
        }

        $data['permission_effective'] = $boxes;
        // the Role field: the user's one staff role
        $data['role_id'] = UserResource::staffRoleId($user);

        return $data;
    }

    /** A new password typed under "Change password" is also kept as the encrypted copy shown on this page. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->data['change_password'] ?? false) && filled($this->data['password'] ?? null) && isset($data['password'])) {
            $data['password_readable'] = (string) $this->data['password'];
        }

        return $data;
    }

    /**
     * Saved with the user, by someone who may change roles and permissions: a box that differs from what the role
     * gives becomes the user's exception (Allow or Deny); one that agrees with the role follows the role again.
     */
    protected function afterSave(): void
    {
        $user = $this->getRecord();

        // the one role picked (before the permissions below, which are compared with the role)
        UserResource::syncStaffRole($user, $this->data['role_id'] ?? null);

        // an admin who changes their own password stays signed in (user, 16 Nov 2026: saving it signed them out,
        // because the session remembers the old password): the session takes the new one
        if (auth()->id() === $user->getKey() && $user->wasChanged('password') && request()->hasSession()) {
            request()->session()->put('password_hash_'.auth()->getDefaultDriver(), $user->getAuthPassword());
        }

        // back to showing the (new) password as text, the tick box clear
        if ($user->wasChanged('password')) {
            $this->data['change_password'] = false;
            $this->data['password'] = null;
            $this->data['current_password'] = $user->password_readable;
        }

        if (! Permissions::allows('settings.roles') || $user->isSuperadmin() || $user->hasAnyRole(['customer', 'driver'])) {
            return;
        }

        $boxes = (array) ($this->data['permission_effective'] ?? []);
        if ($boxes === []) {
            return;
        }

        Permissions::flush();
        $chosen = [];
        foreach (Permissions::fromRoles($user->fresh()) as $permission => $fromRole) {
            $key = str_replace('.', '__', $permission);
            if (array_key_exists($key, $boxes) && (bool) $boxes[$key] !== $fromRole) {
                $chosen[$permission] = $boxes[$key] ? 'allow' : 'deny';
            }
        }

        Permissions::setOverrides($user, $chosen);
    }
}
