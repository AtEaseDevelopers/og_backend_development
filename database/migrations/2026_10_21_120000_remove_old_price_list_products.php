<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the old price list's products (user, 21 Oct 2026): the client's price lists replaced them, so they have no
 * category (Measurement / Item / Chartered Lorry), no prices and were deactivated by the import. Nothing links to
 * them (past orders keep the product name as text). A product with a category or any price is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $removed = DB::table('uoms')
            ->whereNull('category')
            ->where('is_active', false)
            ->whereNotExists(fn ($query) => $query->from('uom_rate_tiers')->whereColumn('uom_rate_tiers.uom_id', 'uoms.id'))
            ->delete();

        echo "  {$removed} old price list product(s) removed.\n";
    }

    public function down(): void
    {
        // the old products are not restored (the old price list can be imported again with og:import-price-list)
    }
};
