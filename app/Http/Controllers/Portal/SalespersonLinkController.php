<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Section A: a customer who opens a salesperson's ordering link has that salesperson
 * fixed on the enquiry they submit next. The token is remembered in the session until
 * the order form is submitted.
 */
class SalespersonLinkController extends Controller
{
    public const SESSION_KEY = 'portal.salesperson_token';

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $salesperson = AssignEnquirySalesperson::resolveByToken($token);

        if (! $salesperson) {
            return redirect()->route('portal.login')->withErrors(['token' => 'This ordering link is no longer valid.']);
        }

        $request->session()->put(self::SESSION_KEY, $token);
        $request->session()->put('url.intended', route('portal.enquiry.create'));

        if ($request->user()) {
            return redirect()->route('portal.enquiry.create')
                ->with('status', 'Orders submitted now will be handled by '.$salesperson->name.'.');
        }

        return redirect()->route('portal.login')
            ->with('status', 'Log in or register to place your order with '.$salesperson->name.'.');
    }
}
