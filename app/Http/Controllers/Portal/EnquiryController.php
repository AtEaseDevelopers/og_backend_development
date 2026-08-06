<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function create(): View
    {
        $branches = Branch::query()->where('is_active', true)->get();

        return view('portal.enquiry-create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'pickup_address' => ['required', 'string'],
            'pickup_maps_url' => ['nullable', 'url'],
            'preferred_delivery_date' => ['nullable', 'date'],
            'special_requirements' => ['nullable', 'string'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.address' => ['required', 'string'],
            'destinations.*.consignee_name' => ['nullable', 'string'],
            'destinations.*.postcode' => ['nullable', 'string'],
            'destinations.*.state' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.uom' => ['nullable', 'string'],
            'items.*.weight' => ['nullable', 'numeric'],
        ]);

        $customerId = $request->user()->customer_id
            ?? $request->user()->customers()->wherePivot('status', 'approved')->value('customers.id');

        PortalEnquiry::query()->create([
            'customer_id' => $customerId,
            'branch_id' => $data['branch_id'],
            'user_id' => $request->user()->id,
            'reference_no' => 'ENQ-'.Str::upper(Str::random(8)),
            'pickup_address' => $data['pickup_address'],
            'pickup_maps_url' => $data['pickup_maps_url'] ?? null,
            'preferred_delivery_date' => $data['preferred_delivery_date'] ?? null,
            'special_requirements' => $data['special_requirements'] ?? null,
            'status' => 'pending',
            'payload' => [
                'destinations' => $data['destinations'],
                'items' => $data['items'],
            ],
        ]);

        return redirect()->route('portal.dashboard')->with('status', 'Enquiry submitted for review.');
    }
}
