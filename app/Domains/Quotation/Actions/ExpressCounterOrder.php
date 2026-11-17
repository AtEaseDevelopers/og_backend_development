<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Enums\OrderType;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Create order -> "Confirm, pay & print" for every payment term (client, 17 Nov 2026): the customer is at the counter
 * with the goods, so the documents are wanted at once.
 *  - a Credit / Term or COD record is confirmed for the customer now (no payment at the counter): its Proforma is
 *    issued and its CSN created — the same steps as "Customer accepted" on the order page;
 *  - a Customer Drop Off record that is paid / released has its goods marked as at the store, so its CSN is created
 *    without the "Ready for CSN" step.
 * Cash records are paid first by RecordOrderCreationPayment.
 */
class ExpressCounterOrder
{
    public function __construct(
        private AcceptQuotation $accept,
        private MarkStoreItemReady $ready,
    ) {}

    /**
     * @param  Collection<int, Quotation>  $records  the records of the order just created
     * @return list<array{number: string, ok: bool, message: string}>
     */
    public function execute(Collection $records, User $actor): array
    {
        $results = [];

        foreach ($records as $record) {
            $record = $record->fresh();

            if (! $record) {
                continue;
            }

            try {
                $messages = [];

                if (in_array($record->orderType(), [OrderType::Term, OrderType::Cod], true) && ! $record->confirmed_at) {
                    $record = $this->accept->execute($record, AcceptQuotation::CHANNEL_ADMIN, null, $actor, 'Confirmed at the counter when the order was created', recordedByStaff: true)->fresh();
                    $messages[] = 'confirmed';
                }

                // the goods are at the counter: no "Ready for CSN" step
                if ($record->isBilledAwaitingItem()) {
                    $this->ready->execute($record, $actor);
                    $record = $record->fresh();
                }

                if ($record->consignmentNotes()->exists()) {
                    $messages[] = 'CSN created';
                } elseif ($messages !== []) {
                    $messages[] = 'no CSN yet (see the order)';
                }

                if ($messages !== []) {
                    $results[] = ['number' => $record->number, 'ok' => true, 'message' => implode(' · ', $messages)];
                }
            } catch (Throwable $e) {
                $results[] = ['number' => $record->number, 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }
}
