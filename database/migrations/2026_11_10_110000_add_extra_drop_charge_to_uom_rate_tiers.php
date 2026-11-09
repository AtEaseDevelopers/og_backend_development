<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra drop-off charge (user, 10 Nov 2026): an optional amount on a price row (per route) charged once for each
 * consignee after the first when an order record has Multiple consignees. Empty = no extra charge for that route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uom_rate_tiers', function (Blueprint $table): void {
            $table->decimal('extra_drop_charge', 12, 2)->nullable()->after('drop_off_min_charge');
        });
    }

    public function down(): void
    {
        Schema::table('uom_rate_tiers', function (Blueprint $table): void {
            $table->dropColumn('extra_drop_charge');
        });
    }
};
