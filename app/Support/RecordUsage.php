<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Whether a master data record has history (client, 16 Nov 2026: "only able to click delete when no history data
 * input" — a record that was used is set Inactive instead). A record is used when any other table points at it: a
 * column named after it ("customer_id", "main_lorry_id" …), or its code where the code is what is stored (a
 * customer's special prices, a product on an order line, a transfer code on a job sheet).
 */
class RecordUsage
{
    /** A record's own details, which go with it: not history. */
    private const OWN_DETAILS = [
        'customers' => ['customer_pics', 'customer_addresses', 'customer_locations', 'consignor_consignees', 'consignor_consignee_customer'],
        // the customers a consignor / consignee is tagged to, the users a transfer code is given to
        'consignor_consignees' => ['consignor_consignee_customer'],
        'transfer_codes' => ['transfer_code_user'],
    ];

    /** Where the record's code (not its id) is what is stored: [table, column, the record's attribute]. */
    private const BY_VALUE = [
        'customers' => [['uom_rate_tiers', 'customer_code', 'code']],
        'uoms' => [['consignment_note_lines', 'uom', 'code'], ['quotation_lines', 'uom', 'code']],
        'transfer_codes' => [['subsheets', 'transfer_code', 'code'], ['job_sheets', 'transfer_code', 'code']],
    ];

    /** @var array<string, list<array{0: string, 1: string}>> */
    private static array $references = [];

    /** @var array<string, bool> */
    private static array $used = [];

    public static function isUsed(Model $record): bool
    {
        $table = $record->getTable();
        $key = $table.':'.$record->getKey();

        return static::$used[$key] ??= static::check($record, $table);
    }

    /** The Delete button of a Master Data list: there only for a record without history. */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Model $record): bool => ! static::isUsed($record))
            ->modalDescription('Only a record that was never used can be deleted. This cannot be undone.')
            ->action(function (Model $record, DeleteAction $action): void {
                try {
                    DB::transaction(function () use ($record): void {
                        foreach (self::OWN_DETAILS[$record->getTable()] ?? [] as $detail) {
                            $column = Str::singular($record->getTable()).'_id';

                            if (Schema::hasTable($detail) && Schema::hasColumn($detail, $column)) {
                                DB::table($detail)->where($column, $record->getKey())->delete();
                            }
                        }

                        $record->delete();
                    });
                } catch (QueryException) {
                    Notification::make()->danger()->title('Cannot delete')->body('This record is used elsewhere. Set it Inactive instead.')->send();
                    $action->halt();
                }

                $action->success();
            });
    }

    private static function check(Model $record, string $table): bool
    {
        foreach (static::references($table) as [$other, $column]) {
            if (DB::table($other)->where($column, $record->getKey())->exists()) {
                return true;
            }
        }

        foreach (self::BY_VALUE[$table] ?? [] as [$other, $column, $attribute]) {
            $value = trim((string) $record->getAttribute($attribute));

            if ($value !== '' && Schema::hasTable($other) && Schema::hasColumn($other, $column) && DB::table($other)->where($column, $value)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The columns of the other tables that point at a table: "<singular>_id", or one ending in "_<singular>_id"
     * (main_lorry_id) — unless that longer name is another table's own ("customer_location_id" is not a location).
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function references(string $table): array
    {
        if (isset(static::$references[$table])) {
            return static::$references[$table];
        }

        // this database's tables only (the server may hold other databases with the same table names)
        $database = DB::connection()->getDatabaseName();
        $all = collect(Schema::getTables());
        $own = $all->filter(fn (array $t) => ($t['schema'] ?? null) === $database);
        $tables = ($own->isNotEmpty() ? $own : $all)->pluck('name')->unique()->values()->all();
        $singular = Str::singular($table);
        $longer = collect($tables)->map(fn (string $t) => Str::singular($t))->filter(fn (string $s) => $s !== $singular && str_ends_with($s, '_'.$singular))->all();
        $found = [];

        foreach ($tables as $other) {
            if ($other === $table || in_array($other, self::OWN_DETAILS[$table] ?? [], true)) {
                continue;
            }

            foreach (Schema::getColumnListing($other) as $column) {
                if (! str_ends_with($column, '_id')) {
                    continue;
                }

                $base = substr($column, 0, -3);

                if ($base !== $singular && ! str_ends_with($base, '_'.$singular)) {
                    continue;
                }

                if (collect($longer)->contains(fn (string $l) => $base === $l || str_ends_with($base, '_'.$l))) {
                    continue;
                }

                $found[] = [$other, $column];
            }
        }

        return static::$references[$table] = $found;
    }

    public static function flush(): void
    {
        static::$references = [];
        static::$used = [];
    }
}
