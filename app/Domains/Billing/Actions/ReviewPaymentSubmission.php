<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Section F: Finance / Admin review of a payment submission.
 *  - verify  = approval level 1 (evidence checked) — only Cash / Pay-at-Counter need it
 *  - approve = approval level 2 / release: the payment + receipt are recorded, paid and
 *              outstanding amounts updated, overpayment creates a Refund Note, and a fully
 *              paid cash order proceeds to Cash Bill + CSN automatically
 *  - reject  = returned to the customer with a mandatory reason (evidence is kept)
 */
class ReviewPaymentSubmission
{
    public function __construct(
        private RecordPayment $recordPayment,
        private CreateRefundNote $refund,
        private GenerateOrderBilling $billing,
        private SendNotification $notify,
        private RefreshOrderPaidAmount $refreshPaid,
    ) {}

    public function verify(PaymentSubmission $submission, User $actor): PaymentSubmission
    {
        $this->assertReviewer($actor);

        if ($submission->status !== PaymentSubmissionStatus::Submitted) {
            throw new InvalidArgumentException('Only newly submitted payments can be verified.');
        }

        if (! $submission->requiresTwoApprovals()) {
            throw new InvalidArgumentException('This payment method needs a single approval — use Approve.');
        }

        $submission->update([
            'status' => PaymentSubmissionStatus::Verified,
            'level1_by' => $actor->id,
            'level1_at' => now(),
        ]);

        return $submission->fresh();
    }

    /** @return array{submission: PaymentSubmission, payment: Payment, billing: ?array} */
    public function approve(PaymentSubmission $submission, User $actor, ?string $remarks = null): array
    {
        $this->assertReviewer($actor);

        if (! $submission->isOpen()) {
            throw new InvalidArgumentException('This payment submission is already '.$submission->status->getLabel().'.');
        }

        if ($submission->requiresTwoApprovals()) {
            if ($submission->status !== PaymentSubmissionStatus::Verified) {
                throw new InvalidArgumentException('Cash / Pay-at-Counter payments need approval level 1 (Verify) first.');
            }

            if ((int) $submission->level1_by === (int) $actor->id && ! $actor->is_hq) {
                throw new InvalidArgumentException('The same user cannot perform both approval levels.');
            }
        }

        $quotation = $submission->quotation()->with(['customer', 'branch'])->firstOrFail();

        $payment = DB::transaction(function () use ($submission, $actor, $remarks, $quotation) {
            $payment = $this->recordPayment->execute([
                'company_id' => $quotation->company_id,
                'source_branch_id' => $quotation->branch_id,
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'payment_submission_id' => $submission->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'method' => $submission->method()?->value,
                'amount' => (float) $submission->amount,
                'expected_amount' => (float) $quotation->total_amount,
                'reference' => $submission->reference,
                'slip_path' => $submission->receipt_path,
                'slip_paths' => $submission->receipt_paths,
                'remarks' => trim(($submission->remarks ?? '').' '.($remarks ?? '')) ?: null,
                'status' => 'completed',
                'receipt_type' => 'official',
            ], $actor);

            $submission->update([
                'status' => PaymentSubmissionStatus::Approved,
                'level1_by' => $submission->level1_by ?? $actor->id,
                'level1_at' => $submission->level1_at ?? now(),
                'level2_by' => $actor->id,
                'level2_at' => now(),
                'payment_id' => $payment->id,
                'remarks' => $remarks ?? $submission->remarks,
            ]);

            $this->refreshPaidAmount($quotation);

            return $payment;
        });

        $quotation->refresh();

        // Overpayment → automatic Refund Note (section F)
        $excess = round((float) $quotation->paid_amount - (float) $quotation->total_amount, 2);

        if ($excess > 0) {
            $this->refund->execute(
                $quotation,
                $excess,
                $actor,
                $payment,
                $submission->bank_account,
                'Overpayment on submission #'.$submission->id.' ('.$submission->reference.')',
            );
        }

        $this->notifyCustomer($submission, $quotation, approved: true);

        // Fully paid cash order → Cash Bill + CSN automatically
        $billing = null;

        if ($quotation->status === QuotationStatus::Confirmed
            && $quotation->orderType() === OrderType::Cash
            && $quotation->isFullyPaid()
            && $quotation->billingStatus() !== BillingStatus::Generated) {
            try {
                $billing = $this->billing->execute($quotation->fresh(), $actor);
            } catch (Throwable $e) {
                $billing = ['ok' => false, 'error' => $e->getMessage(), 'invoices' => collect(), 'csns' => collect()];
            }
        }

        return ['submission' => $submission->fresh(), 'payment' => $payment, 'billing' => $billing];
    }

    public function reject(PaymentSubmission $submission, User $actor, string $reason): PaymentSubmission
    {
        $this->assertReviewer($actor);

        if (! $submission->isOpen()) {
            throw new InvalidArgumentException('This payment submission is already '.$submission->status->getLabel().'.');
        }

        if (blank($reason)) {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $submission->update([
            'status' => PaymentSubmissionStatus::Rejected,
            'rejection_reason' => $reason,
            'level1_by' => $submission->level1_by ?? $actor->id,
            'level1_at' => $submission->level1_at ?? now(),
        ]);

        $quotation = $submission->quotation()->with('customer')->first();

        if ($quotation) {
            $this->notifyCustomer($submission, $quotation, approved: false);
        }

        return $submission->fresh();
    }

    /** Paid amount = sum of the order's completed payments (shared with RecordPayment, see RefreshOrderPaidAmount). */
    public function refreshPaidAmount(Quotation $quotation): void
    {
        $this->refreshPaid->execute($quotation);
    }

    private function notifyCustomer(PaymentSubmission $submission, Quotation $quotation, bool $approved): void
    {
        $customer = $quotation->customer;

        $this->notify->execute(
            event: $approved ? 'payment_approved' : 'payment_rejected',
            recipient: ['type' => 'customer', 'name' => $customer?->company_name, 'email' => $customer?->email, 'phone' => $customer?->phone],
            subject: ($approved ? 'Payment approved for ' : 'Payment submission rejected for ').$quotation->number,
            message: $approved
                ? sprintf(
                    "Your payment of RM %s (%s, ref %s) for order %s has been approved.\nPaid to date: RM %s · Outstanding: RM %s",
                    number_format((float) $submission->amount, 2),
                    $submission->method()?->getLabel(),
                    $submission->reference ?: '—',
                    $quotation->number,
                    number_format((float) $quotation->paid_amount, 2),
                    number_format($quotation->outstandingAmount(), 2),
                )
                : sprintf(
                    "Your payment submission of RM %s (ref %s) for order %s was rejected.\nReason: %s\n\nPlease correct and resubmit in the Customer Portal: %s",
                    number_format((float) $submission->amount, 2),
                    $submission->reference ?: '—',
                    $quotation->number,
                    $submission->rejection_reason,
                    route('portal.quotations.show', $quotation),
                ),
            related: $quotation,
        );
    }

    private function assertReviewer(User $actor): void
    {
        if ($actor->is_hq || $actor->hasAnyRole(['hq_admin', 'branch_manager', 'finance', 'counter'])) {
            return;
        }

        throw new InvalidArgumentException('Only Finance, Counter, Branch Manager or HQ Admin may review payments.');
    }
}
