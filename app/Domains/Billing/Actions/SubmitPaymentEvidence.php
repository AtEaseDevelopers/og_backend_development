<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use InvalidArgumentException;

/**
 * Section F / flowchart step 10: the customer uploads payment proof (or the counter
 * records a payment). Every attempt is linked to the Proforma and kept in history.
 */
class SubmitPaymentEvidence
{
    public function __construct(private GenerateProformaForQuotation $proforma) {}

    /**
     * @param  array{amount: float|string, method: string, payment_date?: ?string, bank_account?: ?string, reference?: ?string, receipt_path?: ?string, remarks?: ?string}  $data
     */
    public function execute(Quotation $quotation, array $data, ?User $submitter = null, string $channel = 'portal'): PaymentSubmission
    {
        if (! in_array($quotation->status, [QuotationStatus::Accepted, QuotationStatus::PendingApproval, QuotationStatus::Confirmed, QuotationStatus::Converted], true)) {
            throw new InvalidArgumentException('Payments can only be submitted for confirmed orders.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $method = PaymentMethod::tryFrom((string) ($data['method'] ?? ''));

        if (! $method) {
            throw new InvalidArgumentException('Select a valid payment method.');
        }

        if (filled($data['reference'] ?? null)) {
            $duplicate = PaymentSubmission::query()
                ->where('quotation_id', $quotation->id)
                ->where('reference', $data['reference'])
                ->whereIn('status', [PaymentSubmissionStatus::Submitted->value, PaymentSubmissionStatus::Verified->value, PaymentSubmissionStatus::Approved->value])
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A payment with reference "'.$data['reference'].'" was already submitted for this order.');
            }
        }

        $proforma = $this->proforma->execute($quotation);

        return PaymentSubmission::query()->create([
            'company_id' => $quotation->company_id,
            'branch_id' => $quotation->branch_id,
            'quotation_id' => $quotation->id,
            'proforma_invoice_id' => $proforma->id,
            'customer_id' => $quotation->customer_id,
            'submitted_by' => $submitter?->id,
            'submitted_channel' => $channel,
            'amount' => $amount,
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'method' => $method,
            'bank_account' => $data['bank_account'] ?? null,
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => PaymentSubmissionStatus::Submitted,
        ]);
    }
}
