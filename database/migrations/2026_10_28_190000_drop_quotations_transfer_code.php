<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order carries no transfer code after all (user, 28 Oct 2026): the code is chosen in Bulk order processing and
 * goes on the subsheet it creates. Undoes 2026_10_28_160000.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotations', 'transfer_code')) {
            // its index first: SQLite (the test suite) cannot drop a column that still has one
            Schema::table('quotations', function (Blueprint $table): void {
                $table->dropIndex(['transfer_code']);
                $table->dropColumn('transfer_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->string('transfer_code', 20)->nullable()->after('expected_delivery_date')->index());
    }
};
