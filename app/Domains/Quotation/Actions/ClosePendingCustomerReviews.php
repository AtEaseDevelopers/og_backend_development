<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\QuotationStatus;
use App\Support\SystemSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Section D: quotations issued without a customer decision move to the
 * "Pending Customer Review" list, and are closed automatically after the configurable
 * number of days (Master Data → System Settings, default 7) without any update.
 */
class ClosePendingCustomerReviews
{
    /** @return array{pending: Collection<int, Quotation>, closed: Collection<int, Quotation>} */
    public function execute(?Carbon $now = null): array
    {
        $now ??= now();
        $days = max(1, SystemSettings::int(SystemSettings::PENDING_REVIEW_DAYS));

        // 1) Sent for more than a day with no response → Pending Customer Review
        $pending = Quotation::query()
            ->where('status', QuotationStatus::Sent->value)
            ->where('sent_at', '<=', $now->copy()->subDay())
            ->get()
            ->each(function (Quotation $quotation): void {
                $quotation->update([
                    'status' => QuotationStatus::PendingReview,
                    'pending_review_since' => $quotation->pending_review_since ?? $quotation->sent_at,
                ]);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'from_status' => QuotationStatus::Sent->value,
                    'to_status' => QuotationStatus::PendingReview->value,
                    'user_id' => null,
                    'remarks' => 'No customer response — moved to Pending Customer Review',
                ]);
            });

        // 2) No update for the configured window → Closed Case
        $cutoff = $now->copy()->subDays($days);

        $closed = Quotation::query()
            ->whereIn('status', [QuotationStatus::PendingReview->value, QuotationStatus::Sent->value])
            ->where('updated_at', '<=', $cutoff)
            ->where(fn ($q) => $q->whereNull('pending_review_since')->orWhere('pending_review_since', '<=', $cutoff))
            ->get()
            ->each(function (Quotation $quotation) use ($days): void {
                $from = $quotation->status->value;

                $quotation->update([
                    'status' => QuotationStatus::Closed,
                    'closed_at' => now(),
                    'closed_reason' => 'No customer response within '.$days.' days',
                ]);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'from_status' => $from,
                    'to_status' => QuotationStatus::Closed->value,
                    'user_id' => null,
                    'remarks' => 'Closed Case — no customer response within '.$days.' days',
                ]);
            });

        return ['pending' => $pending, 'closed' => $closed];
    }

    /** Authorised reopen of a closed case into a fresh draft (same version, history kept). */
    public function reopen(Quotation $quotation, \App\Models\User $actor): Quotation
    {
        if ($quotation->status !== QuotationStatus::Closed) {
            return $quotation;
        }

        $quotation->update([
            'status' => QuotationStatus::Draft,
            'closed_at' => null,
            'closed_reason' => null,
            'pending_review_since' => null,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $quotation->id,
            'from_status' => QuotationStatus::Closed->value,
            'to_status' => QuotationStatus::Draft->value,
            'user_id' => $actor->id,
            'remarks' => 'Closed case reopened by '.$actor->name,
        ]);

        return $quotation->fresh();
    }
}
