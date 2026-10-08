<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Billing\Models\RefundNote;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Corrects a payment already recorded on an order (amount, method, date, reference, slip and note) at any stage,
 * including after billing and CSN creation — e.g. a full payment that should have been a partial one.
 *
 *  - A reason is mandatory; the change is written to the order activity (old → new values + reason).
 *  - Paid / outstanding and the payment billing status are recomputed by ReviewPaymentSubmission::refreshPaidAmount,
 *    the same code the payment approval runs. A confirmed cash order that becomes fully paid is billed exactly as
 *    an approval would bill it.
 *  - Documents already issued for the payment (receipt, Cash Bill / invoice, CSN, and every refund note on the order)
 *    and their AutoCount sync are left untouched: documentsFor() lists them so the user can adjust them separately.
 *  - Cash / Pay at Counter keep their two-user check for non-HQ editors: a counted amount cannot be increased (use
 *    Add payment, which runs verify + approve) and a verified submission whose amount or method changes is reset
 *    to Submitted so it is verified again. The editor is recorded and shown as "Edited by" in the payment history.
 */
class EditRecordedPayment
{
    /** The roles that may review (approve) payments may also correct them — see ReviewPaymentSubmission::assertReviewer. */
    public const ROLES = ['hq_admin', 'branch_manager', 'finance', 'counter'];

    public function __construct(
        private ReviewPaymentSubmission $review,
        private GenerateOrderBilling $billing,
    ) {}

    public static function allows(?User $user): bool
    {
        return (bool) ($user && ($user->is_hq || $user->hasAnyRole(self::ROLES)));
    }

    /**
     * @param  array{amount: float|string, method: string, payment_date?: ?string, reference?: ?string, attachment_path?: ?string, remarks?: ?string}  $data
     * @return array{changes: array<string, array{0: mixed, 1: mixed}>, documents: list<string>, warnings: list<string>, billing: ?array}
     */
    public function execute(Quotation $order, PaymentSubmission|Payment $record, array $data, string $reason, User $actor): array
    {
        if (! self::allows($actor)) {
            throw new InvalidArgumentException('Only Finance, Counter, Branch Manager or HQ Admin may edit payments.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason for the change is required.');
        }

        // work on a fresh copy: the caller's instance may carry stale amounts and many loaded relations
        $order = Quotation::query()->findOrFail($order->getKey());

        [$submission, $payment] = $record instanceof PaymentSubmission
            ? [$record, $record->payment]
            : [$record->submission, $record];

        if (($submission && (int) $submission->quotation_id !== (int) $order->id) || ($payment && (int) $payment->quotation_id !== (int) $order->id)) {
            throw new InvalidArgumentException('This payment does not belong to order '.$order->number.'.');
        }

        if ($submission && ! in_array($submission->status, [PaymentSubmissionStatus::Submitted, PaymentSubmissionStatus::Verified, PaymentSubmissionStatus::Approved], true)) {
            throw new InvalidArgumentException('A '.strtolower((string) $submission->status->getLabel()).' payment cannot be edited.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $method = PaymentMethod::tryFrom((string) ($data['method'] ?? ''));

        if (! $method) {
            throw new InvalidArgumentException('Select a valid payment method.');
        }

        $before = self::snapshot($submission, $payment);
        $after = [
            'amount' => number_format($amount, 2, '.', ''),
            'method' => $method->value,
            // a payment recorded without a submission (COD collection, CSN payment) has no payment date of its own
            'payment_date' => $submission && filled($data['payment_date'] ?? null) ? Carbon::parse($data['payment_date'])->toDateString() : $before['payment_date'],
            'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            'attachment' => self::paths($data['attachment_paths'] ?? ($data['attachment_path'] ?? null)),
            'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
        ];

        $changes = collect($after)
            ->filter(fn ($value, string $key) => self::comparable($value) !== self::comparable($before[$key]))
            ->map(fn ($value, string $key) => [$before[$key], $value])
            ->all();

        if ($changes === []) {
            throw new InvalidArgumentException('Nothing was changed.');
        }

        // Same duplicate-reference rule as SubmitPaymentEvidence
        if (isset($changes['reference']) && $after['reference'] !== null) {
            $duplicate = PaymentSubmission::query()
                ->where('quotation_id', $order->id)
                ->where('reference', $after['reference'])
                ->when($submission, fn ($q) => $q->whereKeyNot($submission->id))
                ->whereIn('status', [PaymentSubmissionStatus::Submitted->value, PaymentSubmissionStatus::Verified->value, PaymentSubmissionStatus::Approved->value])
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A payment with reference "'.$after['reference'].'" was already submitted for this order.');
            }
        }

        // Only completed payments count towards the paid amount (an open submission has no payment yet)
        $counted = $payment && $payment->status === 'completed';

        // Cash / Pay at Counter need verify + approve by two different users (ReviewPaymentSubmission): one user's edit
        // must not raise a counted amount past that check, nor carry a level-1 verification over to a changed payment
        $twoLevel = ! $actor->is_hq
            && ((PaymentMethod::tryFrom((string) $before['method'])?->requiresTwoApprovals() ?? false) || $method->requiresTwoApprovals());

        if ($twoLevel && $counted && $amount > (float) $payment->amount + 0.004) {
            throw new InvalidArgumentException(sprintf(
                'A Cash / Pay at Counter payment cannot be increased by an edit (RM %s → RM %s): it needs verification and approval by two users. Record the extra amount with "Add payment".',
                number_format((float) $payment->amount, 2),
                number_format($amount, 2),
            ));
        }

        $resetVerification = $twoLevel && $submission && ! $counted
            && $submission->status === PaymentSubmissionStatus::Verified
            && (isset($changes['amount']) || isset($changes['method']));

        $warnings = $resetVerification
            ? ['Verification reset: the changed payment must be verified again before it can be approved.']
            : [];

        if ($counted && $amount > (float) $payment->amount) {
            $paidAfter = round((float) Payment::query()->where('quotation_id', $order->id)->where('status', 'completed')->whereKeyNot($payment->id)->sum('amount') + $amount, 2);

            if ($paidAfter > (float) $order->total_amount + 0.005) {
                throw new InvalidArgumentException(sprintf(
                    'This change makes the total paid RM %s, more than the order total RM %s. Record the extra amount with "Add payment" so its refund note is issued.',
                    number_format($paidAfter, 2),
                    number_format((float) $order->total_amount, 2),
                ));
            }
        }

        $documents = self::documentsFor($order, $submission, $payment);

        DB::transaction(function () use ($order, $submission, $payment, $after, $changes, $reason, $actor, $counted, $before, $documents, $method, $resetVerification): void {
            $submission?->update([
                'amount' => $after['amount'],
                'method' => $method,
                'payment_date' => $after['payment_date'],
                'reference' => $after['reference'],
                'receipt_path' => $after['attachment'][0] ?? null,
                'receipt_paths' => $after['attachment'] !== [] ? $after['attachment'] : null,
                'remarks' => $after['remarks'],
                // a verified Cash / Pay at Counter submission whose amount or method changed is verified again
                ...($resetVerification ? [
                    'status' => PaymentSubmissionStatus::Submitted,
                    'level1_by' => null,
                    'level1_at' => null,
                ] : []),
            ]);

            if ($payment) {
                $attributes = [
                    'amount' => $after['amount'],
                    'method' => $method->value,
                    'reference' => $after['reference'],
                    'slip_path' => $after['attachment'][0] ?? null,
                    'slip_paths' => $after['attachment'] !== [] ? $after['attachment'] : null,
                ];

                // the payment's note also carries the approval remarks: only replaced when the note itself changed
                if (isset($changes['remarks'])) {
                    $attributes['remarks'] = $after['remarks'];
                }

                $payment->update($attributes);
            }

            if ($counted) {
                $this->review->refreshPaidAmount($order);
            }

            // the order takes the method of its payment when it had none of its own (see SubmitPaymentEvidence)
            if (isset($changes['method']) && (string) $order->payment_method === (string) $before['method'] && blank($order->portalEnquiry?->payment_method)) {
                $order->update(['payment_method' => $method->value]);
            }

            $order->refresh();

            QuotationStatusLog::query()->create([
                'quotation_id' => $order->id,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'user_id' => $actor->id,
                'remarks' => 'Payment edited · '.self::describe($changes).' · Reason: '.$reason
                    .($counted ? ' · Paid RM '.number_format((float) $order->paid_amount, 2).' · Outstanding RM '.number_format($order->outstandingAmount(), 2) : ''),
                'meta' => [
                    'payment_edit' => [
                        'payment_submission_id' => $submission?->id,
                        'payment_id' => $payment?->id,
                        'old' => $before,
                        'new' => $after,
                        'reason' => $reason,
                        'documents_unchanged' => $documents,
                        // shown as "Edited by" next to "Approved by" in the payment history
                        'edited_by' => ['id' => $actor->id, 'name' => $actor->name],
                        'counted' => $counted,
                        'verification_reset' => $resetVerification,
                    ],
                ],
            ]);

            activity()
                ->performedOn($submission ?? $payment)
                ->causedBy($actor)
                ->withProperties(['quotation_id' => $order->id, 'old' => $before, 'new' => $after, 'reason' => $reason, 'edited_by' => $actor->id, 'verification_reset' => $resetVerification])
                ->log('Payment edited');
        });

        $order->refresh();

        // a refund note issued on this order (also on another payment) is wrong once the order is no longer overpaid
        if ($counted && $order->outstandingAmount() > 0.004) {
            $refunds = RefundNote::query()->where('quotation_id', $order->id)->pluck('number');

            if ($refunds->isNotEmpty()) {
                $warnings[] = sprintf(
                    'Paid is now below the order total (outstanding RM %s) while %s still issued — cancel or adjust %s.',
                    number_format($order->outstandingAmount(), 2),
                    $refunds->count() === 1 ? 'Refund Note '.$refunds->first().' is' : 'Refund Notes '.$refunds->implode(', ').' are',
                    $refunds->count() === 1 ? 'it' : 'them',
                );
            }
        }

        // Same rule as ReviewPaymentSubmission::approve: a fully paid, confirmed cash order is billed (Cash Bill + CSN)
        $billing = null;

        if ($counted
            && $order->status === QuotationStatus::Confirmed
            && $order->orderType() === OrderType::Cash
            && $order->isFullyPaid()
            && $order->billingStatus() !== BillingStatus::Generated) {
            try {
                $billing = $this->billing->execute($order->fresh(), $actor);
            } catch (Throwable $e) {
                $billing = ['ok' => false, 'error' => $e->getMessage(), 'invoices' => collect(), 'csns' => collect()];
            }
        }

        return ['changes' => $changes, 'documents' => $documents, 'warnings' => $warnings, 'billing' => $billing];
    }

    /**
     * Documents already issued for this payment that an edit does not change.
     *
     * @return list<string>
     */
    public static function documentsFor(Quotation $order, ?PaymentSubmission $submission, ?Payment $payment): array
    {
        $payment ??= $submission?->payment;

        if (! $payment) {
            return [];
        }

        $payment->loadMissing(['receipt', 'cashBill', 'invoice', 'consignmentNote']);
        $docs = [];
        $synced = fn ($doc) => ($doc->autocount_sync_status ?? null) === 'synced' ? ' · synced to AutoCount' : '';

        if ($payment->receipt) {
            $docs[] = 'Receipt '.$payment->receipt->number.' (RM '.number_format((float) $payment->receipt->amount, 2).')'.$synced($payment->receipt);
        }

        foreach (collect([$payment->cashBill, $payment->invoice])->filter()->unique('id') as $invoice) {
            $docs[] = ($invoice->isCashBill() ? 'Cash Bill ' : 'Invoice ').$invoice->number.' (RM '.number_format((float) $invoice->total_amount, 2).')'.$synced($invoice);
        }

        $csns = $payment->consignmentNote ? collect([$payment->consignmentNote]) : $order->consignmentNotes()->get();

        foreach ($csns as $csn) {
            $docs[] = 'CSN '.$csn->number.' (payment status: '.($csn->payment_status?->getLabel() ?? '—').')';
        }

        // every refund note on the order: one issued on another payment becomes wrong too when this edit lowers paid
        $refunds = RefundNote::query()
            ->where(fn ($q) => $q->where('quotation_id', $order->id)->orWhere('payment_id', $payment->id))
            ->orderBy('id')
            ->get();

        foreach ($refunds as $refund) {
            $docs[] = 'Refund Note '.$refund->number.' (RM '.number_format((float) $refund->amount, 2)
                .((int) $refund->payment_id === (int) $payment->id ? '' : ', overpayment on another payment').')'.$synced($refund);
        }

        return $docs;
    }

    /** @return list<string> uploaded file paths (a single path or a list), blanks dropped */
    public static function paths(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path) => trim($path))
            ->unique()
            ->values()
            ->all();
    }

    private static function comparable(mixed $value): string
    {
        return is_array($value) ? (string) json_encode(array_values($value)) : (string) $value;
    }

    /** @return array{amount: string, method: ?string, payment_date: ?string, reference: ?string, attachment: list<string>, remarks: ?string} */
    public static function snapshot(?PaymentSubmission $submission, ?Payment $payment): array
    {
        if ($submission) {
            return [
                'amount' => number_format((float) $submission->amount, 2, '.', ''),
                'method' => $submission->method()?->value,
                'payment_date' => $submission->payment_date?->toDateString(),
                'reference' => filled($submission->reference) ? $submission->reference : null,
                'attachment' => $submission->files(),
                'remarks' => filled($submission->remarks) ? $submission->remarks : null,
            ];
        }

        return [
            'amount' => number_format((float) $payment?->amount, 2, '.', ''),
            'method' => $payment?->method,
            'payment_date' => $payment?->created_at?->toDateString(),
            'reference' => filled($payment?->reference) ? $payment->reference : null,
            'attachment' => $payment?->files() ?? [],
            'remarks' => filled($payment?->remarks) ? $payment->remarks : null,
        ];
    }

    /** "RM 310.00 → RM 200.00 · method Cash → Bank Transfer · reference — → ABC123" */
    private static function describe(array $changes): string
    {
        return collect($changes)->map(function (array $pair, string $key) {
            [$old, $new] = $pair;

            return match ($key) {
                'amount' => 'RM '.number_format((float) $old, 2).' → RM '.number_format((float) $new, 2),
                'method' => 'method '.(PaymentMethod::tryFrom((string) $old)?->getLabel() ?? ($old ?: '—')).' → '.(PaymentMethod::tryFrom((string) $new)?->getLabel() ?? ($new ?: '—')),
                'payment_date' => 'date '.($old ? Carbon::parse($old)->format('d/m/Y') : '—').' → '.($new ? Carbon::parse($new)->format('d/m/Y') : '—'),
                'reference' => 'reference '.($old ?: '—').' → '.($new ?: '—'),
                'attachment' => 'attachments '.count((array) $old).' → '.count((array) $new).' file(s)',
                'remarks' => 'note updated',
                default => $key.' changed',
            };
        })->values()->implode(' · ');
    }
}
