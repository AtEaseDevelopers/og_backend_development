<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product priced per kg (client, 1 Nov 2026: "Weight * 0.14" in the price list — pallets over 1,000 kg): its range
 * price is the rate per kg and a line's unit price is that rate × the piece's weight. The import used to mark these
 * only by "(per kg)" in the product name; the flag replaces that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uoms', fn (Blueprint $table) => $table->boolean('priced_per_kg')->default(false)->after('max_weight'));

        DB::table('uoms')->where('name', 'like', '%(per kg)')->update(['priced_per_kg' => true]);
    }

    public function down(): void
    {
        Schema::table('uoms', fn (Blueprint $table) => $table->dropColumn('priced_per_kg'));
    }
};
