<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A commission line's calculated amount (user, 21 Oct 2026): admin changes the amount per CSN on the batch's lorry /
 * job sheet pages; the system's amount is kept next to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_line_items', function (Blueprint $table) {
            $table->decimal('system_amount', 12, 2)->nullable()->after('amount');
        });

        DB::table('commission_line_items')->update(['system_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('commission_line_items', function (Blueprint $table) {
            $table->dropColumn('system_amount');
        });
    }
};
