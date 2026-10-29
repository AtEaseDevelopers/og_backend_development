<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalMailer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Section P: a portal user resets a forgotten password through a link sent by email. */
class PasswordResetController extends Controller
{
    public function showRequest(): View
    {
        return view('portal.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::query()->where('email', $data['email'])->first();

        // only customer portal logins; the answer is the same either way so emails cannot be probed
        if ($user && $user->hasRole('customer')) {
            Password::broker()->sendResetLink(['email' => $user->email], function (User $user, string $token) {
                $url = route('portal.password.reset', ['token' => $token, 'email' => $user->email]);
                $minutes = config('auth.passwords.users.expire', 60);

                PortalMailer::send(
                    $user->email,
                    'Reset your O&G Customer Portal password',
                    "Hello {$user->name},\n\nOpen this link to choose a new password (valid for {$minutes} minutes):\n{$url}\n\nIf you did not ask for this, you can ignore this email.",
                );
            });
        }

        return back()->with('status', 'If that email has a portal account, a password reset link is on its way.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('portal.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                // the link reached their inbox, so the email is confirmed too
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
        }

        return redirect()->route('portal.login')->with('status', 'Your password has been reset. Please sign in.');
    }
}
