<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Billing\Actions\OnlinePaymentFlow;
use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Billing\Models\OnlinePayment;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Sections F and P: the customer pays an order from the portal: payment slip upload, pay at counter, or
 * online through the payment gateway.
 */
class PaymentSubmissionController extends Controller
{
    use UsesPortalCustomer;

    /** Payment slip / evidence upload (bank transfer, eWallet, cheque, cash). */
    public function store(Request $request, Quotation $quotation, SubmitPaymentEvidence $submit): RedirectResponse
    {
        $this->authorizeCustomerRecord($quotation->customer_id);

        $methods = collect(PaymentMethod::options(customerFacing: true))->except([PaymentMethod::Online->value, PaymentMethod::Counter->value]);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in($methods->keys()->all())],
            'payment_date' => ['nullable', 'date'],
            'bank_account' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], ['receipt.required' => 'Attach the payment slip.']);

        $data['receipt_path'] = $request->file('receipt')->store('payment-proofs/'.$quotation->id, 'public');

        try {
            $submit->execute($quotation, $data, $request->user(), 'portal');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Payment slip submitted. Our team will verify it shortly.');
    }

    /** The customer will pay at the branch counter: Counter staff verify and approve it there. */
    public function counter(Request $request, Quotation $quotation, SubmitPaymentEvidence $submit): RedirectResponse
    {
        $this->authorizeCustomerRecord($quotation->customer_id);

        if ($quotation->paymentSubmissions()->where('method', PaymentMethod::Counter->value)->whereIn('status', ['submitted', 'verified'])->exists()) {
            return back()->withErrors(['payment' => 'A counter payment is already waiting for this order.']);
        }

        try {
            $submit->execute($quotation, [
                'amount' => $quotation->outstandingAmount(),
                'method' => PaymentMethod::Counter->value,
                'payment_date' => now()->toDateString(),
                'reference' => 'CTR-'.Str::upper(Str::random(6)),
                'remarks' => 'Customer will pay at the '.($quotation->branch?->name ?? 'branch').' counter.',
            ], $request->user(), 'portal');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'Noted: pay RM '.number_format($quotation->outstandingAmount(), 2).' at the '.($quotation->branch?->name ?? 'branch').' counter and quote order '.$quotation->number.'.');
    }

    public function online(Request $request, Quotation $quotation, OnlinePaymentFlow $flow): RedirectResponse
    {
        $this->authorizeCustomerRecord($quotation->customer_id);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        try {
            $started = $flow->start($quotation, (float) $data['amount'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->away($started['url']);
    }

    /** Sandbox bank page ("simulate" mode) standing in for the live gateway. */
    public function gateway(Request $request, string $reference): View
    {
        abort_unless($request->hasValidSignature(), 403, 'This payment link has expired. Start the payment again.');
        $payment = $this->ownPayment($reference);

        return view('portal.payment-gateway', [
            'payment' => $payment->load('quotation'),
            'token' => $this->token($payment),
        ]);
    }

    public function gatewayResult(Request $request, string $reference, OnlinePaymentFlow $flow): RedirectResponse
    {
        $payment = $this->ownPayment($reference);
        abort_unless(hash_equals($this->token($payment), (string) $request->input('token')), 403);

        $paid = $request->input('result') === 'paid';
        $payment = $flow->complete(
            $payment,
            $paid,
            $paid ? 'SBX'.Str::upper(Str::random(10)) : null,
            $paid ? null : ($request->input('result') === 'cancelled' ? 'Cancelled by customer' : 'Declined by bank (sandbox)'),
            ['mode' => config('og.payment_gateway.mode'), 'result' => $request->input('result')],
        );

        return redirect()->route('portal.quotations.show', $payment->quotation_id)->with('status', $payment->status === OnlinePayment::STATUS_PAID
            ? 'Online payment of RM '.number_format((float) $payment->amount, 2).' received (ref '.$payment->reference.'). Finance will post it to your order shortly.'
            : 'Online payment failed: '.$payment->failure_reason.'. You can try again or choose another payment method.');
    }

    private function ownPayment(string $reference): OnlinePayment
    {
        $payment = OnlinePayment::query()->where('reference', $reference)->firstOrFail();
        $this->authorizeCustomerRecord($payment->customer_id);

        return $payment;
    }

    private function token(OnlinePayment $payment): string
    {
        return hash_hmac('sha256', $payment->reference.'|'.$payment->amount, (string) config('app.key'));
    }
}
