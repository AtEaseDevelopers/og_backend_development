<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\CsnStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Scan-to-claim (section H / flowchart step 14).
 *
 * Admin, Store User and Driver can scan a CSN's QR token to claim it. Before allowing a
 * claim the CSN must be active, still pending lorry assignment (or pending a transfer
 * claim), the user must be permitted, and nobody else may have claimed it. The check and
 * the write happen under a row lock so two scans can never claim the same CSN.
 *
 * A driver's claim assigns the CSN to the driver's lorry immediately; an admin / store
 * claim reserves the CSN (it leaves the unassigned queue) until a lorry is assigned.
 */
class ClaimCsn
{
    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_STORE = 'store';

    public const CHANNEL_DRIVER = 'driver';

    public function __construct(private AssignCsnToLorry $assign) {}

    public function executeByQrToken(string $qrToken, User $user, ?Lorry $lorry = null, ?string $operatingDate = null): ConsignmentNote
    {
        $csn = ConsignmentNote::query()->where('qr_token', trim($qrToken))->first();

        if (! $csn) {
            throw new InvalidArgumentException('No CSN matches the scanned code.');
        }

        return $this->execute($csn, $user, $lorry, $operatingDate);
    }

    public function execute(ConsignmentNote $csn, User $user, ?Lorry $lorry = null, ?string $operatingDate = null): ConsignmentNote
    {
        $channel = $this->channelFor($user);

        return DB::transaction(function () use ($csn, $user, $lorry, $operatingDate, $channel) {
            /** @var ConsignmentNote $locked */
            $locked = ConsignmentNote::query()->lockForUpdate()->findOrFail($csn->id);

            if ($locked->status === CsnStatus::Cancelled || $locked->cancelled_at) {
                throw new InvalidArgumentException('CSN '.$locked->number.' is not active.');
            }

            $this->assertPermitted($locked, $user);

            // Transferred job: only the receiving driver may claim it.
            if ($locked->transfer_claim_pending) {
                $currentDriverId = $locked->deliveryOrder()->value('driver_id');

                if ($channel !== self::CHANNEL_DRIVER || (int) $user->driver_id !== (int) $currentDriverId) {
                    throw new InvalidArgumentException('This transferred CSN can only be claimed by its newly assigned driver.');
                }

                $locked->update([
                    'transfer_claim_pending' => false,
                    'claimed_by' => $user->id,
                    'claimed_at' => now(),
                    'claim_channel' => $channel,
                ]);

                return $locked->fresh(['deliveryOrder.lorry', 'deliveryOrder.driver']);
            }

            if (! $locked->isPendingAssignment()) {
                throw new InvalidArgumentException('CSN '.$locked->number.' is no longer pending lorry assignment ('.$locked->status->getLabel().').');
            }

            if ($locked->isClaimed() && (int) $locked->claimed_by !== (int) $user->id) {
                $locked->loadMissing('claimer');

                throw new InvalidArgumentException(sprintf(
                    'CSN %s was already claimed by %s on %s.',
                    $locked->number,
                    $locked->claimer?->name ?? 'another user',
                    $locked->claimed_at?->format('d/m/Y H:i'),
                ));
            }

            if ($locked->deliveryOrder()->exists()) {
                throw new InvalidArgumentException('CSN '.$locked->number.' already has a Delivery Order.');
            }

            $locked->update([
                'claimed_by' => $user->id,
                'claimed_at' => now(),
                'claim_channel' => $channel,
            ]);

            if ($channel === self::CHANNEL_DRIVER) {
                $lorry ??= $this->lorryForDriver($user);

                $this->assign->execute(
                    csn: $locked,
                    lorry: $lorry,
                    operatingDate: $operatingDate,
                    driverId: $user->driver_id,
                    actor: $user,
                );
            }

            return $locked->fresh(['deliveryOrder.lorry', 'deliveryOrder.driver', 'claimer']);
        });
    }

    public function channelFor(User $user): string
    {
        if ($user->driver_id) {
            return self::CHANNEL_DRIVER;
        }

        if ($user->hasRole('storekeeper')) {
            return self::CHANNEL_STORE;
        }

        return self::CHANNEL_ADMIN;
    }

    private function assertPermitted(ConsignmentNote $csn, User $user): void
    {
        if (! $user->is_active) {
            throw new InvalidArgumentException('Your account is not active.');
        }

        $permitted = $user->is_hq
            || $user->driver_id
            || $user->hasAnyRole(['hq_admin', 'branch_manager', 'dispatcher', 'storekeeper', 'counter']);

        if (! $permitted) {
            throw new InvalidArgumentException('You are not permitted to claim consignment notes.');
        }

        if ($user->is_hq || $user->hasRole('hq_admin')) {
            return;
        }

        $branchIds = $user->driver_id
            ? array_filter([$user->driver?->branch_id])
            : $user->branches()->pluck('branches.id')->all();

        // Shared dispatch: a lorry from any branch may execute; only restrict when the user
        // has explicit branch access that excludes this CSN's source branch.
        if ($branchIds !== [] && ! $user->driver_id && ! in_array($csn->source_branch_id, $branchIds, true)) {
            throw new InvalidArgumentException('This CSN belongs to a branch you do not have access to.');
        }
    }

    private function lorryForDriver(User $user): Lorry
    {
        $lorry = Lorry::query()
            ->where('default_driver_id', $user->driver_id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (! $lorry) {
            throw new InvalidArgumentException('No active lorry is linked to your driver profile. Ask Admin to assign a lorry.');
        }

        return $lorry;
    }
}
