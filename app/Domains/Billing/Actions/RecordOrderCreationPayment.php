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
     * @param  array{method: string, payment_date: string, reference?: ?string, receipt_paths?: list<string>, amount?: ?float}  $payment  amount: the order's part of a short payment (null = in full), paid to its Cash records in turn; a record paid less than its total is a partial payment (it stays Pending payment)
     * @return list<array{number: string, ok: bool, message: string}>
     */
    public function execute(Collection $records, array $payment, User $actor): array
    {
        $results = [];
        $budget = isset($payment['amount']) ? round((float) $payment['amount'], 2) : null;

        foreach ($records as $record) {
            $record = $record->fresh();

            if (! $record || $record->orderType() !== OrderType::Cash) {
                continue;
            }

            $total = round((float) $record->total_amount, 2);
            $pay = $budget === null ? $total : round(min($budget, $total), 2);

            // nothing left of a short payment for this record: it waits for its payment
            if ($pay <= 0) {
                continue;
            }

            $budget = $budget === null ? null : round($budget - $pay, 2);
            $partial = $pay < $total - 0.004;

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
                    'amount' => $pay,
                    'method' => $payment['method'],
                    'payment_date' => $payment['payment_date'],
                    'reference' => filled($payment['reference'] ?? null) ? trim((string) $payment['reference']) : null,
                    'receipt_path' => $paths[0] ?? null,
                    'receipt_paths' => $paths !== [] ? $paths : null,
                    'remarks' => ($partial ? 'Partial payment (RM '.number_format($pay, 2).' of RM '.number_format($total, 2).').' : 'Full payment.').' Recorded when the order was created.',
                ], $actor, 'admin');

                if ($submission->requiresTwoApprovals()) {
                    $this->review->verify($submission, $actor);
                    $results[] = ['number' => $record->number, 'ok' => true, 'message' => 'paid RM '.number_format((float) $submission->amount, 2).($partial ? ' of RM '.number_format($total, 2).' (partial)' : '').' · verified · a second user approves it'.($partial ? '' : ' to create the CSN')];

                    continue;
                }

                $result = $this->review->approve($submission, $actor);
                $billing = $result['billing'] ?? null;

                $results[] = [
                    'number' => $record->number,
                    'ok' => ($billing['ok'] ?? true) !== false,
                    'message' => 'paid RM '.number_format((float) $submission->amount, 2).($partial ? ' of RM '.number_format($total, 2).' (partial · stays Pending payment)' : '').match (true) {
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
