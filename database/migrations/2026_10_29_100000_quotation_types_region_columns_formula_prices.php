<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's two quotation types (user, 29 Oct 2026): a price list (effective date, SST line) and a customer
 * quotation ("Please find…", terms, contact person); destinations that print as one quotation column
 * ("S'BAN / MELAKA"); a quantity range priced as a fixed amount plus a rate per unit ("(250+4.0X)").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', fn (Blueprint $table) => $table->string('type', 20)->default('customer')->after('number'));
        Schema::table('locations', fn (Blueprint $table) => $table->string('quote_column', 60)->nullable()->after('name'));
        Schema::table('uom_rate_tiers', fn (Blueprint $table) => $table->decimal('fixed_charge', 12, 2)->nullable()->after('price'));
    }

    public function down(): void
    {
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('quote_column'));
        Schema::table('uom_rate_tiers', fn (Blueprint $table) => $table->dropColumn('fixed_charge'));
    }
};
