<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several pick-up / drop-off locations on one record and its CSN (user, 27 Oct 2026): a JSON list of stops, each
 * with its type, location and DO number (and, on the CSN, when the driver scanned it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotations', 'stops')) {
            Schema::table('quotations', fn (Blueprint $table) => $table->json('stops')->nullable()->after('customer_do_number'));
        }

        if (! Schema::hasColumn('consignment_notes', 'stops')) {
            Schema::table('consignment_notes', fn (Blueprint $table) => $table->json('stops')->nullable()->after('other_do_numbers'));
        }
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('stops'));
        Schema::table('consignment_notes', fn (Blueprint $table) => $table->dropColumn('stops'));
    }
};
