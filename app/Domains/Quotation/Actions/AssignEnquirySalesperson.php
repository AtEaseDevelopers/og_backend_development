<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Section A: one enquiry is attended by exactly one salesperson.
 *  - orders from a salesperson's link fix the salesperson automatically (locked)
 *  - general portal / walk-in enquiries get their salesperson from the dropdown
 *  - every order record created from the enquiry inherits the same salesperson + SA location
 */
class AssignEnquirySalesperson
{
    public function execute(
        PortalEnquiry $enquiry,
        User $salesperson,
        ?User $actor = null,
        bool $lock = false,
        ?string $source = null,
    ): PortalEnquiry {
        if (! $salesperson->isSalesperson() && ! $salesperson->is_hq && ! $salesperson->hasAnyRole(['hq_admin', 'branch_manager'])) {
            throw new InvalidArgumentException($salesperson->name.' is not a salesperson.');
        }

        if ($enquiry->salesperson_locked && $enquiry->salesperson_id && (int) $enquiry->salesperson_id !== (int) $salesperson->id) {
            if (! ($actor?->isSuperadmin())) {
                throw new InvalidArgumentException('This enquiry is locked to '.$enquiry->salesperson?->name.' and cannot be reassigned.');
            }
        }

        return DB::transaction(function () use ($enquiry, $salesperson, $actor, $lock, $source) {
            $enquiry->update([
                'salesperson_id' => $salesperson->id,
                'sa_location_id' => $salesperson->sa_location_id ?? $enquiry->sa_location_id,
                'salesperson_locked' => $lock || $enquiry->salesperson_locked,
                'source' => $source ?? $enquiry->source,
                'attended_by' => $enquiry->attended_by ?? $actor?->id,
                'attended_at' => $enquiry->attended_at ?? ($actor ? now() : null),
            ]);

            // All order records from the same enquiry remain under the same salesperson.
            $enquiry->quotations()->update([
                'salesperson_id' => $salesperson->id,
                'sa_location_id' => $salesperson->sa_location_id,
                'salesperson_locked' => true,
            ]);

            activity()
                ->performedOn($enquiry)
                ->causedBy($actor)
                ->withProperties(['salesperson_id' => $salesperson->id, 'locked' => $lock])
                ->log('Salesperson assigned: '.$salesperson->name);

            return $enquiry->fresh(['salesperson', 'saLocation']);
        });
    }

    public static function resolveByToken(?string $token): ?User
    {
        if (blank($token)) {
            return null;
        }

        return User::query()
            ->where('ordering_token', $token)
            ->where('is_active', true)
            ->first();
    }
}
