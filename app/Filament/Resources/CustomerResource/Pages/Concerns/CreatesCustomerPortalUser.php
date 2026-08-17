<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Domains\MasterData\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesCustomerPortalUser
{
    protected function createPortalUser(Customer $customer): void
    {
        $state = $this->form->getState();

        $email = trim((string) ($state['portal_email'] ?? ''));
        $password = (string) ($state['portal_password'] ?? '');
        $name = trim((string) ($state['portal_user_name'] ?? $customer->company_name ?? 'Customer'));

        if ($email === '' || $password === '') {
            return;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'phone' => $customer->phone,
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);

        $user->assignRole('customer');

        $status = ($customer->portal_approved ?? false) ? 'approved' : 'pending';

        $user->customers()->syncWithoutDetaching([
            $customer->id => ['status' => $status],
        ]);
    }
}
