<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product entered on an order is kept as an order line even before it has a price (no price-list rate
 * yet, or a price still to be agreed): unit_price NULL = "not priced yet" (its line_total stays 0).
 * Relaxes the NOT NULL constraint only; existing rows and the default are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table): void {
            $table->decimal('unit_price', 15, 2)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        // NOT NULL again: a line without a price is stored as 0.00
        DB::table('quotation_lines')->whereNull('unit_price')->update(['unit_price' => 0]);

        Schema::table('quotation_lines', function (Blueprint $table): void {
            $table->decimal('unit_price', 15, 2)->default(0)->change();
        });
    }
};
