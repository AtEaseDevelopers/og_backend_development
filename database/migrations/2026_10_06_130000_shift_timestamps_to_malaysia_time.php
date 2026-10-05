<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app ran in UTC until 2026-10-06; it now runs in Malaysia time (Asia/Kuala_Lumpur, UTC+8).
 * Every date-time value written so far is UTC wall-clock time, so shift all DATETIME / TIMESTAMP
 * columns forward by 8 hours once, so old and new records read the same way.
 *
 * DATE-only columns are left alone (they carry no time). Integer epoch columns (sessions,
 * jobs, cache) are unaffected. One UPDATE per table sets every date-time column explicitly,
 * so ON UPDATE CURRENT_TIMESTAMP columns cannot fire.
 */
return new class extends Migration
{
    private const HOURS = 8;

    /** Framework bookkeeping tables that must not be touched. */
    private const SKIP_TABLES = ['migrations'];

    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $sign): void
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            return; // sqlite test databases start empty
        }

        $columns = collect(DB::select(
            "select table_name as t, column_name as c
               from information_schema.columns
              where table_schema = database()
                and data_type in ('datetime', 'timestamp')
              order by table_name, ordinal_position"
        ))->groupBy('t');

        $interval = $sign === '+' ? 'DATE_ADD' : 'DATE_SUB';

        foreach ($columns as $table => $cols) {
            if (in_array($table, self::SKIP_TABLES, true)) {
                continue;
            }

            $assignments = $cols
                ->map(fn ($col) => sprintf('`%1$s` = %2$s(`%1$s`, INTERVAL %3$d HOUR)', $col->c, $interval, self::HOURS))
                ->implode(', ');

            DB::statement(sprintf('UPDATE `%s` SET %s', $table, $assignments));
        }
    }
};
