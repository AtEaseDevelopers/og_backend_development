<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who at the customer accepted the quotation (asked by "Accept by customer", user 20 Oct 2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('accepted_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('accepted_by'));
    }
};
