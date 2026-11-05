<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\OnlinePayment;
use App\Domains\Quotation\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Section P: an online payment through the gateway. start() opens a transaction and returns the gateway page
 * to send the customer to; complete() takes the gateway's result. A paid transaction becomes a payment
 * submission (method Online) that Finance confirms with a single approval, like any other online payment.
 *
 * Mode "simulate" uses the sandbox bank page in the portal; a live provider plugs in at gatewayUrl().
 */
class OnlinePaymentFlow
{
    public function __construct(private SubmitPaymentEvidence $submit) {}

    public static function enabled(): bool
    {
        return (bool) config('og.payment_gateway.enabled', true);
    }

    public function start(Quotation $quotation, float $amount, User $user): array
    {
        if (! self::enabled()) {
            throw new InvalidArgumentException('Online payment is not available at the moment.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $quotation->outstandingAmount()) {
            throw new InvalidArgumentException('Enter an amount up to the outstanding RM '.number_format($quotation->outstandingAmount(), 2).'.');
        }

        $payment = OnlinePayment::query()->create([
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'user_id' => $user->id,
            'reference' => 'OP'.now()->format('ymd').Str::upper(Str::random(8)),
            'amount' => $amount,
            'gateway' => (string) config('og.payment_gateway.name', 'FPX (sandbox)'),
            'status' => OnlinePayment::STATUS_INITIATED,
        ]);

        return ['payment' => $payment, 'url' => $this->gatewayUrl($payment)];
    }

    public function gatewayUrl(OnlinePayment $payment): string
    {
        return URL::temporarySignedRoute('portal.online-payments.gateway', now()->addMinutes(30), ['reference' => $payment->reference]);
    }

    public function complete(OnlinePayment $payment, bool $paid, ?string $gatewayTxnId = null, ?string $reason = null, array $payload = []): OnlinePayment
    {
        if ($payment->status !== OnlinePayment::STATUS_INITIATED) {
            return $payment; // the gateway may call back more than once
        }

        if (! $paid) {
            $payment->update([
                'status' => OnlinePayment::STATUS_FAILED,
                'gateway_txn_id' => $gatewayTxnId,
                'failure_reason' => $reason ?: 'Payment was not completed.',
                'payload' => $payload ?: null,
            ]);

            return $payment->fresh();
        }

        return DB::transaction(function () use ($payment, $gatewayTxnId, $payload) {
            $submission = $this->submit->execute($payment->quotation, [
                'amount' => (float) $payment->amount,
                'method' => 'online',
                'payment_date' => now()->toDateString(),
                'reference' => $payment->reference,
                'bank_account' => $payment->gateway,
                'remarks' => 'Paid online via '.$payment->gateway.($gatewayTxnId ? ' (transaction '.$gatewayTxnId.')' : ''),
            ], $payment->user, 'portal');

            $payment->update([
                'status' => OnlinePayment::STATUS_PAID,
                'gateway_txn_id' => $gatewayTxnId,
                'payment_submission_id' => $submission->id,
                'payload' => $payload ?: null,
                'paid_at' => now(),
            ]);

            return $payment->fresh();
        });
    }
}
