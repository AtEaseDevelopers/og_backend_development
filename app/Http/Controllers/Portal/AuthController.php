<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Customer;
use App\Support\PortalSelection;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $approved = $user->customers()->wherePivot('status', 'approved')->exists()
            || ($user->customer_id && $user->customer?->portal_approved);

        if (! $approved) {
            Auth::logout();

            return back()->withErrors(['email' => 'Portal access not approved.']);
        }

        return redirect()->route('portal.select-branch');
    }

    public function showRegister(): View
    {
        return view('portal.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'company_name' => ['required', 'string'],
            'brn' => ['required', 'string'],
            'tin' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $customer = Customer::query()->firstOrCreate(
            ['brn' => $data['brn'], 'branch_id' => $data['branch_id']],
            [
                'company_name' => $data['company_name'],
                'tin' => $data['tin'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'active',
                'portal_approved' => false,
            ]
        );

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);
        $user->assignRole('customer');
        $user->customers()->syncWithoutDetaching([
            $customer->id => ['status' => 'pending'],
        ]);

        return redirect()->route('portal.login')
            ->with('status', 'Registration submitted. Await admin approval before login.');
    }

    public function logout(Request $request): RedirectResponse
    {
        PortalSelection::clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
