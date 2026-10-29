<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPortalLink;
use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalMailer;
use App\Support\PortalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
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

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'This login has been deactivated.'])->onlyInput('email');
        }

        if (! $user->email_verified_at) {
            Auth::logout();

            return back()
                ->withErrors(['email' => 'Please verify your email address first. Check your inbox for the verification link.'])
                ->with('unverified_email', $user->email)
                ->onlyInput('email');
        }

        if ($user->approvedCustomerIds()->isEmpty()) {
            $pending = $user->customers()->wherePivot('status', CustomerPortalLink::STATUS_PENDING)->exists();
            Auth::logout();

            return back()->withErrors(['email' => $pending
                ? 'Your registration is waiting for O&G approval. You will receive an email once it is approved.'
                : 'Portal access not approved.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        PortalSelection::clear();

        return redirect()->route('portal.select-company');
    }

    public function showRegister(): View
    {
        return view('portal.register', [
            'branches' => \App\Domains\MasterData\Models\Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'company_name' => ['required', 'string', 'max:255'],
            'brn' => ['required', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $customer = static::findOrCreateCustomer($data, $data['email']);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'],
                'customer_id' => $customer->id,
                'is_active' => true,
            ]);
            $user->requiresEmailVerification = true;
            $user->save();
            $user->assignRole('customer');
            $user->customers()->syncWithoutDetaching([
                $customer->id => ['status' => CustomerPortalLink::STATUS_PENDING],
            ]);

            return $user;
        });

        static::sendVerificationEmail($user);

        return redirect()->route('portal.login')
            ->with('status', 'Registration received. We sent a verification link to '.$user->email.'. After you verify, O&G will review and approve your account.');
    }

    /**
     * The customer account of a BRN in a branch: an existing one is linked (its details are kept; a new login
     * waits for approval), otherwise a new unapproved customer is created with the person in charge.
     *
     * @param  array{company_name: string, brn: string, tin?: ?string, phone?: ?string, address?: ?string, branch_id: int|string, name?: ?string}  $data
     */
    public static function findOrCreateCustomer(array $data, string $email): Customer
    {
        $brn = strtoupper(trim($data['brn']));

        $existing = Customer::query()
            ->where('branch_id', $data['branch_id'])
            ->whereRaw('UPPER(TRIM(brn)) = ?', [$brn])
            ->first();

        if ($existing) {
            return $existing;
        }

        $customer = Customer::query()->create([
            'company_id' => Company::query()->where('branch_id', $data['branch_id'])->where('is_active', true)->orderBy('id')->value('id'),
            'branch_id' => $data['branch_id'],
            'code' => CustomerForm::generateDebtorCode($data['company_name']),
            'company_name' => $data['company_name'],
            'brn' => $brn,
            'tin' => $data['tin'] ?? null,
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'attention' => $data['name'] ?? null,
            'status' => 'active',
            'portal_approved' => false,
            'email_notifications' => true,
        ]);

        if (filled($data['name'] ?? null)) {
            $customer->pics()->create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $email,
                'is_default' => true,
            ]);
        }

        return $customer;
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'This verification link is invalid or has expired.');

        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $approved = $user->approvedCustomerIds()->isNotEmpty();

        return redirect()->route('portal.login')->with('status', $approved
            ? 'Email verified. You can sign in now.'
            : 'Email verified. O&G will review your registration; you can sign in once it is approved.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user && ! $user->email_verified_at) {
            static::sendVerificationEmail($user);
        }

        return back()->with('status', 'If that email is waiting for verification, a new link has been sent.');
    }

    public static function sendVerificationEmail(User $user): void
    {
        $url = URL::temporarySignedRoute('portal.verify-email', now()->addHours(24), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        PortalMailer::send(
            $user->email,
            'Verify your email for the O&G Customer Portal',
            "Hello {$user->name},\n\nPlease confirm your email address by opening this link (valid for 24 hours):\n{$url}\n\nIf you did not register, you can ignore this email.",
        );
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
