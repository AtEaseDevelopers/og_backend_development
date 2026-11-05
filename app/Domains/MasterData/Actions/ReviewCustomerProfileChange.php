<?php

namespace App\Domains\MasterData\Actions;

use App\Domains\MasterData\Models\CustomerProfileChange;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Section P: Admin re-verifies a major profile change asked for in the customer portal. */
class ReviewCustomerProfileChange
{
    public function __construct(private SendNotification $notify) {}

    public function approve(CustomerProfileChange $change, User $actor, ?string $remarks = null): CustomerProfileChange
    {
        $this->assertPending($change);

        DB::transaction(function () use ($change, $actor, $remarks) {
            $updates = collect($change->changes ?? [])
                ->only(CustomerProfileChange::MAJOR_FIELDS)
                ->map(fn ($pair) => array_pad((array) $pair, 2, null)[1])
                ->all();

            $change->customer->update($updates);

            $change->update([
                'status' => CustomerProfileChange::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);
        });

        $this->tellRequester($change, 'approved', $remarks);

        return $change->fresh();
    }

    public function reject(CustomerProfileChange $change, User $actor, string $remarks): CustomerProfileChange
    {
        $this->assertPending($change);

        if (blank($remarks)) {
            throw new InvalidArgumentException('Give the customer a reason for rejecting the change.');
        }

        $change->update([
            'status' => CustomerProfileChange::STATUS_REJECTED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        $this->tellRequester($change, 'rejected', $remarks);

        return $change->fresh();
    }

    private function assertPending(CustomerProfileChange $change): void
    {
        if ($change->status !== CustomerProfileChange::STATUS_PENDING) {
            throw new InvalidArgumentException('This change has already been '.$change->statusLabel().'.');
        }
    }

    private function tellRequester(CustomerProfileChange $change, string $outcome, ?string $remarks): void
    {
        $user = $change->user;

        if (! $user?->email) {
            return;
        }

        $this->notify->execute(
            'customer_profile_change_'.$outcome,
            ['name' => $user->name, 'email' => $user->email, 'type' => 'customer'],
            'Profile change '.$outcome.' — '.$change->customer?->company_name,
            "Your request to change the {$change->sectionLabel()} of {$change->customer?->company_name} was {$outcome}.\n\n{$change->summary()}"
                .($remarks ? "\n\nRemarks: {$remarks}" : ''),
            $change->customer,
            [NotificationLog::CHANNEL_EMAIL],
        );
    }
}
