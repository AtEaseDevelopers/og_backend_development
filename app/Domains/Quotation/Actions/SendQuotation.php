<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Support\QuotationMatrix;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section D: send the quotation to the customer through WhatsApp and/or email with a
 * Customer Portal link. Credit-term customers whose consent letter says "no pricing
 * reconfirmation required" are accepted automatically at the agreed special price.
 */
class SendQuotation
{
    public function __construct(
        private SendNotification $notify,
        private AcceptQuotation $accept,
    ) {}

    /** @param  list<string>  $channels */
    public function execute(Quotation $quotation, User $actor, array $channels = [NotificationLog::CHANNEL_EMAIL, NotificationLog::CHANNEL_WHATSAPP]): Quotation
    {
        $quotation->loadMissing(['customer', 'branch']);

        if (! in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Negotiation, QuotationStatus::Sent, QuotationStatus::PendingReview], true)) {
            throw new InvalidArgumentException('Quotation cannot be sent in status "'.$quotation->status->getLabel().'".');
        }

        if (! $quotation->isLatestVersion()) {
            throw new InvalidArgumentException('Only the latest quotation version can be sent.');
        }

        if ((float) $quotation->total_amount <= 0) {
            throw new InvalidArgumentException('Enter pricing before sending the quotation.');
        }

        // a product kept on the order without a price yet would go out unpriced
        if (($unpriced = QuotationMatrix::unpricedItems($quotation)) !== []) {
            throw new InvalidArgumentException('Enter a price for '.implode(', ', $unpriced).' under Items & pricing before sending the quotation.');
        }

        if (! $quotation->customer) {
            throw new InvalidArgumentException('Quotation has no customer.');
        }

        $quotation = DB::transaction(function () use ($quotation, $actor) {
            $from = $quotation->status->value;

            $quotation->update([
                'status' => QuotationStatus::Sent,
                'sent_at' => now(),
                'pending_review_since' => now(),
                'closed_at' => null,
                'closed_reason' => null,
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => QuotationStatus::Sent->value,
                'user_id' => $actor->id,
                'remarks' => 'Quotation version '.$quotation->version.' issued to customer',
            ]);

            return $quotation->fresh(['customer', 'branch']);
        });

        $customer = $quotation->customer;
        $isRevision = $quotation->version > 1;

        $this->notify->execute(
            event: $isRevision ? 'quotation_revised' : 'quotation_ready',
            recipient: ['type' => 'customer', 'name' => $customer->company_name, 'email' => $customer->email, 'phone' => $customer->phone],
            subject: ($isRevision ? 'Revised quotation ' : 'Quotation ').$quotation->number.' is ready for your review',
            message: sprintf(
                "Dear %s,\n\n%s %s (version %d) for RM %s is ready for your review.\nPlease log in to the Customer Portal to accept or reject it:\n%s\n\nValid until: %s",
                $customer->company_name,
                $isRevision ? 'Your revised quotation' : 'Your quotation',
                $quotation->number,
                $quotation->version,
                number_format((float) $quotation->total_amount, 2),
                route('portal.quotations.show', $quotation),
                $quotation->valid_until?->format('d/m/Y') ?? '—',
            ),
            related: $quotation,
            channels: $channels,
        );

        // Consent letter: no reconfirmation required for the agreed special price (section C / D)
        if ($customer->consentSkipsReconfirmation()
            && in_array($quotation->pricing_source, ['special', 'previous'], true)) {
            return $this->accept->execute(
                $quotation,
                AcceptQuotation::CHANNEL_CONSENT,
                $customer->company_name,
                $actor,
                'Consent letter on file: '.$customer->consent_letter_type
                    .($customer->consent_valid_until ? ' (valid until '.$customer->consent_valid_until->format('d/m/Y').')' : ''),
            );
        }

        return $quotation;
    }
}
