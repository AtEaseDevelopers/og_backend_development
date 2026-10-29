<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPortalLink;
use App\Http\Controllers\Controller;
use App\Support\PortalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Section P: after login the user picks the company account (BRN) to work in. Only approved accounts can be
 * opened; one login may hold several BRNs across branches and may ask for access to another BRN.
 */
class AccountSelectionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $approvedIds = $user->approvedCustomerIds();

        $links = $user->customers()
            ->with('branch')
            ->orderBy('company_name')
            ->get();

        // legacy login linked only through users.customer_id
        if ($links->isEmpty() && $approvedIds->isNotEmpty()) {
            $links = Customer::query()->with('branch')->whereIn('id', $approvedIds)->get()
                ->each(fn (Customer $c) => $c->setRelation('pivot', (object) ['status' => CustomerPortalLink::STATUS_APPROVED]));
        }

        if (! $request->boolean('switch') && $approvedIds->count() === 1 && $links->count() === 1 && ! session()->has('errors')) {
            PortalSelection::setCustomer(Customer::query()->findOrFail($approvedIds->first()));

            return $this->toIntendedPortalPage();
        }

        return view('portal.select-company', [
            'links' => $links,
            'approvedIds' => $approvedIds,
            'currentId' => PortalSelection::customerId(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        abort_unless($request->user()->approvedCustomerIds()->contains((int) $data['customer_id']), 403);

        PortalSelection::setCustomer(Customer::query()->findOrFail($data['customer_id']));

        return $this->toIntendedPortalPage();
    }

    /** Back to the portal page the user wanted; an intended admin URL (shared session) is dropped. */
    private function toIntendedPortalPage(): RedirectResponse
    {
        $intended = (string) session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        return str_starts_with($path, '/portal/') && $path !== '/portal/login'
            ? redirect()->to($intended)
            : redirect()->route('portal.dashboard');
    }

    /** Ask for access to another company (BRN); O&G approves it like a new registration. */
    public function requestAccess(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'brn' => ['required', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $data['name'] = $user->name;

        $result = DB::transaction(function () use ($data, $user) {
            $customer = AuthController::findOrCreateCustomer($data, $user->email);
            $existing = $user->customers()->where('customers.id', $customer->id)->first();

            if ($existing && $existing->pivot->status !== CustomerPortalLink::STATUS_REJECTED) {
                return $existing->pivot->status;
            }

            $user->customers()->syncWithoutDetaching([
                $customer->id => ['status' => CustomerPortalLink::STATUS_PENDING, 'reviewed_by' => null, 'reviewed_at' => null, 'review_remarks' => null],
            ]);

            return 'requested';
        });

        return redirect()->route('portal.select-company', ['switch' => 1])->with('status', match ($result) {
            CustomerPortalLink::STATUS_APPROVED => 'You already have access to that company.',
            CustomerPortalLink::STATUS_PENDING => 'Your request for that company is already waiting for approval.',
            default => 'Access requested. O&G will review it; the company appears here once approved.',
        });
    }

    /**
     * Customer-specific ordering link from Admin (signed URL): opens the order form for that customer account
     * once the user signs in with a login approved for it.
     */
    public function orderLink(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'This ordering link is invalid.');

        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('portal.login')
                ->with('status', 'Sign in or register to place an order for '.$customer->company_name.'.');
        }

        if (! $request->user()->approvedCustomerIds()->contains($customer->id)) {
            return redirect()->route('portal.select-company', ['switch' => 1])
                ->withErrors(['account' => 'This ordering link is for '.$customer->company_name.', which your login cannot access yet. Request access below.']);
        }

        PortalSelection::setCustomer($customer);

        return redirect()->route('portal.enquiry.create');
    }
}
