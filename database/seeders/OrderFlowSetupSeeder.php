<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DropOffMinCharge;
use App\Domains\MasterData\Models\SaLocation;
use App\Enums\DropOffType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sections A/B setup: one SA location (with CSN prefix) per branch, every salesperson
 * linked to their branch's SA location with a customer ordering link, and default
 * drop-off minimum charges. Safe to re-run.
 */
class OrderFlowSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Branch::query()->get() as $branch) {
            $location = SaLocation::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $branch->code.'-SA1'],
                [
                    'company_id' => $branch->defaultCompany()?->id,
                    'name' => $branch->name.' Sales Area 1',
                    'csn_prefix' => $branch->code.'S',
                    'address' => $branch->address,
                    'is_active' => true,
                ],
            );

            // demo salesperson per branch (password: password) when none exists yet
            $hasSalesperson = User::query()->role('salesperson')
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id))
                ->exists();

            if (! $hasSalesperson) {
                $email = 'sales.'.strtolower($branch->code).'@og.local';
                $demo = User::query()->firstOrCreate(
                    ['email' => $email],
                    ['name' => $branch->code.' Salesperson', 'password' => 'password', 'is_active' => true, 'phone' => '0123456789'],
                );
                $demo->assignRole('salesperson');
                $demo->assignToBranch($branch, true);
            }

            $salespersons = User::query()
                ->role('salesperson')
                ->whereNull('sa_location_id')
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id))
                ->get();

            foreach ($salespersons as $salesperson) {
                $salesperson->forceFill(['sa_location_id' => $location->id])->saveQuietly();
                $salesperson->ensureOrderingToken();
            }
        }

        // remaining salespersons without any branch: attach to the first SA location
        $fallback = SaLocation::query()->orderBy('id')->first();

        if ($fallback) {
            User::query()->role('salesperson')->whereNull('sa_location_id')->get()
                ->each(function (User $user) use ($fallback): void {
                    $user->forceFill(['sa_location_id' => $fallback->id])->saveQuietly();
                    $user->ensureOrderingToken();
                });
        }

        foreach ([DropOffType::Construction->value => 80, DropOffType::Supermarket->value => 50, DropOffType::Other->value => 0] as $type => $charge) {
            DropOffMinCharge::query()->firstOrCreate(
                ['branch_id' => null, 'drop_off_type' => $type],
                ['minimum_charge' => $charge, 'is_active' => $charge > 0, 'remarks' => 'Default — formula pending O&G confirmation'],
            );
        }

        $this->command?->info('SA locations: '.SaLocation::query()->count().' · salespersons with ordering links: '.User::query()->whereNotNull('ordering_token')->count());
    }
}
