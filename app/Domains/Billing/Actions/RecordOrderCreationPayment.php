<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Quotation\Actions\AcceptQuotation;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\OrderType;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The payment recorded while creating an order (Create order → "Record payment now", user, 20 Oct 2026): each Cash
 * record of the new order is confirmed for the customer (admin recorded), paid in full and — once the payment is
 * approved — billed, which issues its Cash Bill and creates its CSN. The same steps as the order page: Customer
 * accepted, then Add payment (SubmitPaymentEvidence + ReviewPaymentSubmission). A Cash / Pay at Counter payment
 * still needs a second user's approval before billing (two approval levels).
 */
class RecordOrderCreationPayment
{
    public function __construct(
        private AcceptQuotation $accept,
        private SubmitPaymentEvidence $submit,
        private ReviewPaymentSubmission $review,
    ) {}

    /**
     * @param  Collection<int, Quotation>  $records  the records of the order just created
     * @param  array{method: string, payment_date: string, reference?: ?string, receipt_paths?: list<string>}  $payment
     * @return list<array{number: string, ok: bool, message: string}>
     */
    public function execute(Collection $records, array $payment, User $actor): array
    {
        $results = [];

        foreach ($records as $record) {
            $record = $record->fresh();

            if (! $record || $record->orderType() !== OrderType::Cash) {
                continue;
            }

            try {
                $confirmed = $this->accept->execute(
                    $record,
                    AcceptQuotation::CHANNEL_ADMIN,
                    null,
                    $actor,
                    'Paid when the order was created',
                    recordedByStaff: true,
                )->fresh();

                $paths = array_values(array_filter($payment['receipt_paths'] ?? []));
                $submission = $this->submit->execute($confirmed, [
                    'amount' => round((float) $confirmed->total_amount, 2),
                    'method' => $payment['method'],
                    'payment_date' => $payment['payment_date'],
                    'reference' => filled($payment['reference'] ?? null) ? trim((string) $payment['reference']) : null,
                    'receipt_path' => $paths[0] ?? null,
                    'receipt_paths' => $paths !== [] ? $paths : null,
                    'remarks' => 'Full payment. Recorded when the order was created.',
                ], $actor, 'admin');

                if ($submission->requiresTwoApprovals()) {
                    $this->review->verify($submission, $actor);
                    $results[] = ['number' => $record->number, 'ok' => true, 'message' => 'paid RM '.number_format((float) $submission->amount, 2).' · verified · a second user approves it to create the CSN'];

                    continue;
                }

                $result = $this->review->approve($submission, $actor);
                $billing = $result['billing'] ?? null;

                $results[] = [
                    'number' => $record->number,
                    'ok' => ($billing['ok'] ?? true) !== false,
                    'message' => 'paid RM '.number_format((float) $submission->amount, 2).match (true) {
                        ($billing['ok'] ?? null) === true => ' · Cash Bill issued, CSN created',
                        ($billing['ok'] ?? null) === false => ' · billing failed: '.($billing['error'] ?? 'see the order'),
                        default => '',
                    },
                ];
            } catch (Throwable $e) {
                $results[] = ['number' => $record->number, 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }
}
