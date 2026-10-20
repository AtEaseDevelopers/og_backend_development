<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The lorries of the client's Lorry & Driver Details list (user, 21 Oct 2026): the current lorries are cleared and the
 * list's lorries entered (number, type, tonne as capacity in kg) for the KL branch. A lorry with job sheets cannot be
 * deleted (its trips keep it): it is deactivated instead, so it is no longer offered. Its maintenance records go with
 * a deleted lorry. The drivers are entered on Master Data → Drivers (the lorry number links them).
 */
return new class extends Migration
{
    private const LORRIES = [
        ['registration_no' => 'JCM 6456', 'type' => '24FT', 'tonne' => 10],
        ['registration_no' => 'JDW 3887', 'type' => '24FT', 'tonne' => 10],
    ];

    public function up(): void
    {
        $keep = array_map(fn (array $lorry) => strtoupper(str_replace(' ', '', $lorry['registration_no'])), self::LORRIES);
        $old = DB::table('lorries')->get(['id', 'registration_no'])
            ->reject(fn ($lorry) => in_array(strtoupper(str_replace(' ', '', (string) $lorry->registration_no)), $keep, true));
        $withTrips = DB::table('job_sheets')->whereIn('lorry_id', $old->pluck('id'))->distinct()->pluck('lorry_id')->all();

        $deleted = DB::table('lorries')->whereIn('id', $old->pluck('id')->diff($withTrips)->all())->delete();
        $deactivated = $withTrips === [] ? 0 : DB::table('lorries')->whereIn('id', $withTrips)->update(['is_active' => false, 'updated_at' => now()]);

        $branch = DB::table('branches')->where('code', 'KL')->first() ?? DB::table('branches')->orderBy('id')->first();
        $companyId = $branch
            ? DB::table('companies')->where('branch_id', $branch->id)->orderByDesc('is_active')->orderBy('id')->value('id')
            : null;
        $added = 0;

        foreach (self::LORRIES as $lorry) {
            $exists = DB::table('lorries')->whereRaw("REPLACE(UPPER(registration_no), ' ', '') = ?", [strtoupper(str_replace(' ', '', $lorry['registration_no']))])->exists();

            if ($exists) {
                continue;
            }

            DB::table('lorries')->insert([
                'company_id' => $companyId,
                'branch_id' => $branch?->id,
                'registration_no' => $lorry['registration_no'],
                'type' => $lorry['type'],
                'capacity' => $lorry['tonne'] * 1000,
                'status' => 'available',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
        }

        echo "  {$deleted} old lorr".($deleted === 1 ? 'y' : 'ies')." removed, {$deactivated} deactivated (they have job sheets), {$added} added from the driver list.\n";
    }

    public function down(): void
    {
        // the removed lorries are not restored
    }
};
