<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorizeCustomer($request, $quotation);
        $quotation->load(['destinations', 'lines', 'branch', 'customer']);

        return view('portal.quotation-show', compact('quotation'));
    }

    public function confirm(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);

        if (! in_array($quotation->status, [QuotationStatus::Sent, QuotationStatus::Draft, QuotationStatus::Accepted], true)) {
            return back()->withErrors(['status' => 'Quotation cannot be confirmed in its current status.']);
        }

        $from = $quotation->status->value;
        $quotation->update([
            'status' => QuotationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $from,
            'to_status' => QuotationStatus::Confirmed->value,
            'user_id' => $request->user()->id,
            'remarks' => 'Confirmed via customer portal',
        ]);

        return back()->with('status', 'Quotation confirmed.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string']]);

        $from = $quotation->status->value;
        $quotation->update([
            'status' => QuotationStatus::Rejected,
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $from,
            'to_status' => QuotationStatus::Rejected->value,
            'user_id' => $request->user()->id,
            'remarks' => $data['rejection_reason'] ?? 'Rejected via portal',
        ]);

        return back()->with('status', 'Quotation rejected.');
    }

    public function requestAmendment(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);
        $data = $request->validate(['remarks' => ['required', 'string']]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => $quotation->status->value,
            'to_status' => $quotation->status->value,
            'user_id' => $request->user()->id,
            'remarks' => 'Amendment request: '.$data['remarks'],
        ]);

        return back()->with('status', 'Amendment request submitted.');
    }

    private function authorizeCustomer(Request $request, Quotation $quotation): void
    {
        $customerIds = $request->user()->customers()->pluck('customers.id');
        if ($request->user()->customer_id) {
            $customerIds->push($request->user()->customer_id);
        }

        abort_unless($customerIds->contains($quotation->customer_id), 403);
    }
}
