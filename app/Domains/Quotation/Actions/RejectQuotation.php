<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\QuotationRejectionCategory;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section D: the customer rejects the quotation or asks for negotiation. The reason
 * category is recorded; negotiation moves the record to "Negotiation Required" so
 * sales can revise into a new version. The assigned salesperson is notified.
 */
class RejectQuotation
{
    public function __construct(private SendNotification $notify) {}

    public function execute(
        Quotation $quotation,
        QuotationRejectionCategory $category,
        ?string $reason,
        ?User $actor = null,
        string $channel = 'portal',
    ): Quotation {
        $quotation->loadMissing(['customer', 'salesperson']);

        $isAdmin = $actor && ! $actor->customer_id;

        if (! ($quotation->status->isCustomerActionable() || ($isAdmin && ! $quotation->status->isTerminal()))) {
            throw new InvalidArgumentException('Quotation cannot be rejected in status "'.$quotation->status->getLabel().'".');
        }

        $target = $category->isNegotiation() ? QuotationStatus::Negotiation : QuotationStatus::Rejected;

        $quotation = DB::transaction(function () use ($quotation, $category, $reason, $actor, $channel, $target) {
            $from = $quotation->status->value;

            $quotation->update([
                'status' => $target,
                'rejection_category' => $category->value,
                'rejection_reason' => $reason,
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => $target->value,
                'user_id' => $actor?->id,
                'remarks' => $category->getLabel().($reason ? ': '.$reason : '').' (via '.$channel.')',
            ]);

            return $quotation->fresh(['customer', 'salesperson']);
        });

        $salesperson = $quotation->salesperson;

        if ($salesperson) {
            $this->notify->execute(
                event: $target === QuotationStatus::Negotiation ? 'quotation_negotiation' : 'quotation_rejected',
                recipient: ['type' => 'user', 'name' => $salesperson->name, 'email' => $salesperson->email, 'phone' => $salesperson->phone],
                subject: ($target === QuotationStatus::Negotiation ? 'Negotiation requested on ' : 'Quotation rejected: ').$quotation->number,
                message: sprintf(
                    "%s responded to quotation %s (version %d).\nOutcome: %s\nReason: %s",
                    $quotation->customer?->company_name,
                    $quotation->number,
                    $quotation->version,
                    $category->getLabel(),
                    $reason ?: '—',
                ),
                related: $quotation,
            );
        }

        return $quotation;
    }
}
