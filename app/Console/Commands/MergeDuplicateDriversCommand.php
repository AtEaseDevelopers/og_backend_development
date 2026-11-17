<?php

namespace App\Console\Commands;

use App\Domains\MasterData\Models\Driver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drivers keyed in twice before the form refused it (client, 17 Nov 2026) are merged into one: the oldest is kept,
 * its empty fields are filled from the others, everything that points at the others (job sheets, delivery orders,
 * commission, payments, the login …) is moved to it, and the others are deleted.
 *
 * Same driver = same company and the same IC (dashes and spaces aside), or the same name where no IC says otherwise.
 * Without --apply nothing is changed: the command only lists what it would merge.
 */
class MergeDuplicateDriversCommand extends Command
{
    protected $signature = 'og:merge-duplicate-drivers {--apply : merge for real (without it: list only)}';

    protected $description = 'List (or with --apply, merge) drivers that were keyed in twice';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $plain = fn (?string $v) => mb_strtoupper(str_replace(['-', ' '], '', (string) $v));
        $name = fn (Driver $d) => mb_strtoupper(trim((string) $d->name));

        $groups = collect();
        foreach (Driver::query()->orderBy('id')->get()->groupBy('company_id') as $companyId => $drivers) {
            // the ICs seen under each name: a driver without an IC joins its namesake only when there is no doubt who that is
            $icsOfName = $drivers->groupBy($name)->map(fn (Collection $g) => $g->map(fn ($d) => $plain($d->ic_number))->filter()->unique()->values());

            foreach ($drivers as $driver) {
                $ic = $plain($driver->ic_number);
                $ics = $icsOfName[$name($driver)];
                if ($ic === '' && $ics->count() > 1) {
                    $this->warn("Left alone: #{$driver->id} {$driver->name} has no IC and {$ics->count()} drivers of that name have different ICs.");

                    continue;
                }
                $key = $companyId.'|'.($ic !== '' ? 'ic:'.$ic : ($ics->count() === 1 ? 'ic:'.$ics[0] : 'name:'.$name($driver)));
                $groups[$key] = ($groups[$key] ?? collect())->push($driver);
            }
        }
        $groups = $groups->filter(fn (Collection $g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate drivers.');

            return self::SUCCESS;
        }

        $columns = $this->driverColumns();
        $merged = 0;
        foreach ($groups as $group) {
            $keep = $group->first();
            $extras = $group->slice(1);
            $ids = $extras->pluck('id')->all();
            $this->line("Keep #{$keep->id} {$keep->name}".($keep->ic_number ? " ({$keep->ic_number})" : '').' <- merge '.$extras->map(fn ($d) => "#{$d->id} {$d->name}")->implode(', '));

            $moves = [];
            foreach ($columns as [$table, $column]) {
                if ($count = DB::table($table)->whereIn($column, $ids)->count()) {
                    $moves[] = "{$table}.{$column}: {$count}";
                }
            }
            $this->line('    moves: '.($moves ? implode(', ', $moves) : 'nothing points at the extra'));

            // two logins on one driver: a person decides which one stays
            if (DB::table('users')->whereIn('driver_id', $group->pluck('id')->all())->count() > 1) {
                $this->warn('    Left alone: more than one login is linked to these drivers. Remove one login first.');

                continue;
            }

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($keep, $extras, $ids, $columns) {
                foreach ($extras as $extra) {
                    foreach ($extra->getAttributes() as $field => $value) {
                        if (! in_array($field, ['id', 'created_at', 'updated_at'], true) && filled($value) && blank($keep->getAttribute($field))) {
                            $keep->setAttribute($field, $value);
                        }
                    }
                }
                $keep->save();
                foreach ($columns as [$table, $column]) {
                    DB::table($table)->whereIn($column, $ids)->update([$column => $keep->id]);
                }
                Driver::query()->whereKey($ids)->delete();
            });
            $merged += count($ids);
        }

        $this->info($apply ? "Merged: {$merged} extra driver(s) removed." : 'Nothing was changed. Run again with --apply to merge.');

        return self::SUCCESS;
    }

    /** Every column that holds a driver's id (driver_id, main_driver_id, default_driver_id …), found from the database. */
    private function driverColumns(): array
    {
        $found = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if ($table !== 'drivers' && str_ends_with($column, 'driver_id')) {
                    $found[] = [$table, $column];
                }
            }
        }

        return $found;
    }
}
