<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Section F: the customer uploads payment proof against the order's proforma. */
class PaymentSubmissionController extends Controller
{
    public function store(Request $request, Quotation $quotation, SubmitPaymentEvidence $submit): RedirectResponse
    {
        $customerIds = $request->user()->customers()->pluck('customers.id');
        if ($request->user()->customer_id) {
            $customerIds->push($request->user()->customer_id);
        }
        abort_unless($customerIds->contains($quotation->customer_id), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::options(customerFacing: true)))],
            'payment_date' => ['nullable', 'date'],
            'bank_account' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('payment-proofs/'.$quotation->id, 'public');
        }

        try {
            $submit->execute($quotation, $data, $request->user(), 'portal');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Payment submitted. Our team will verify it shortly.');
    }
}
