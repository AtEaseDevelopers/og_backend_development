<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section D: revise a quotation into a new version. The previous version is kept for
 * reference (status Superseded) and is never overwritten. Accepted / confirmed versions
 * can only be reopened by a superadmin (authorised reopen → new version).
 */
class ReviseQuotation
{
    public function execute(Quotation $quotation, User $actor, ?string $reason = null): Quotation
    {
        if (! $quotation->isLatestVersion()) {
            throw new InvalidArgumentException('A newer version of this quotation already exists.');
        }

        if ($quotation->status === QuotationStatus::Converted) {
            throw new InvalidArgumentException('Billing has been issued for this order; it can no longer be revised.');
        }

        if ($quotation->status->isConfirmedOrLater() && ! $actor->isSuperadmin()) {
            throw new InvalidArgumentException('This version was confirmed by the customer. Only a superadmin may reopen it into a new version.');
        }

        return DB::transaction(function () use ($quotation, $actor, $reason) {
            $quotation->loadMissing(['destinations', 'lines']);

            $rootId = $quotation->rootId();
            $rootNumber = Quotation::query()->whereKey($rootId)->value('number') ?? $quotation->number;
            $baseNumber = preg_replace('/-V\d+$/', '', $rootNumber);
            $nextVersion = (int) Quotation::query()
                ->where(fn ($q) => $q->where('root_quotation_id', $rootId)->orWhere('id', $rootId))
                ->max('version') + 1;

            $revision = $quotation->replicate([
                'number', 'version', 'status', 'accepted_version', 'confirmation_channel', 'confirmed_by_name',
                'consent_evidence', 'sent_at', 'confirmed_at', 'converted_at', 'pending_review_since',
                'closed_at', 'closed_reason', 'rejection_category', 'rejection_reason',
                'released_at', 'released_by', 'release_reason', 'release_outstanding',
                'billing_status', 'billing_error', 'billed_at', 'paid_amount',
                'locked_by', 'locked_at', 'lock_heartbeat_at',
            ]);

            $revision->fill([
                'number' => $baseNumber.'-V'.$nextVersion,
                'version' => $nextVersion,
                'root_quotation_id' => $rootId,
                'revision_of_id' => $quotation->id,
                'status' => QuotationStatus::Draft,
                'billing_status' => BillingStatus::NotStarted,
                'paid_amount' => 0,
                'created_by' => $actor->id,
            ]);
            $revision->save();

            foreach ($quotation->destinations as $destination) {
                $copy = $destination->replicate(['quotation_id']);
                $copy->quotation_id = $revision->id;
                $copy->save();

                foreach ($quotation->lines->where('quotation_destination_id', $destination->id) as $line) {
                    $lineCopy = $line->replicate(['quotation_id', 'quotation_destination_id']);
                    $lineCopy->quotation_id = $revision->id;
                    $lineCopy->quotation_destination_id = $copy->id;
                    $lineCopy->save();
                }
            }

            foreach ($quotation->lines->whereNull('quotation_destination_id') as $line) {
                $lineCopy = $line->replicate(['quotation_id']);
                $lineCopy->quotation_id = $revision->id;
                $lineCopy->save();
            }

            $from = $quotation->status->value;
            $quotation->update(['status' => QuotationStatus::Superseded]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => QuotationStatus::Superseded->value,
                'user_id' => $actor->id,
                'remarks' => 'Replaced by version '.$nextVersion.($reason ? ' — '.$reason : ''),
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $revision->id,
                'from_status' => null,
                'to_status' => QuotationStatus::Draft->value,
                'user_id' => $actor->id,
                'remarks' => 'Version '.$nextVersion.' created from '.$quotation->number.($reason ? ' — '.$reason : ''),
            ]);

            return $revision->fresh(['destinations', 'lines']);
        });
    }
}
