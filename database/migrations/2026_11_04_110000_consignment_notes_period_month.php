<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A CSN moved to another month (user, 3 Nov 2026): the month (YYYY-MM) it counts in for the commission batch and
 * the term billing; blank = its own dates as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->string('period_month', 7)->nullable()->after('billing_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('consignment_notes', fn (Blueprint $table) => $table->dropColumn('period_month'));
    }
};
