<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order's transfer code (user, 28 Oct 2026): chosen on the order form; Bulk order processing filters by it and
 * puts a subsheet with that code on the lorry instead of the CSN itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('transfer_code', 20)->nullable()->after('expected_delivery_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('transfer_code'));
    }
};
