<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the drivers entered before the driver list's new fields (user, 21 Oct 2026: the current driver data are
 * removed; the drivers are entered again with their lorry and attendant). Only drivers created before 21 Oct 2026
 * are removed, so a driver added since is kept. A driver still on a commission slip, commission PO or COD
 * reconciliation is kept (those records need it). Delivery orders, job sheets, lorries (default driver) and driver
 * logins keep their rows without the driver (set empty by the database).
 */
return new class extends Migration
{
    public function up(): void
    {
        $kept = collect(['commission_slips', 'commission_purchase_orders', 'cod_reconciliations'])
            ->filter(fn (string $table) => DB::getSchemaBuilder()->hasTable($table))
            ->flatMap(fn (string $table) => DB::table($table)->whereNotNull('driver_id')->distinct()->pluck('driver_id'))
            ->unique()
            ->values()
            ->all();

        $removed = DB::table('drivers')
            ->where('created_at', '<', '2026-10-21 00:00:00')
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();

        echo "  {$removed} old driver(s) removed".($kept !== [] ? '; '.count($kept).' kept (on commission / COD records)' : '').".\n";
    }

    public function down(): void
    {
        // the removed drivers are not restored
    }
};
