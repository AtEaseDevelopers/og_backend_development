<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products & pricing per company (user, 28 Oct 2026): Measurements, Items, Chartered Lorry and Drop-off Min Charges
 * belong to one company (uoms.branch_id, drop_off_min_charges.branch_id) and each company sees its own. Everything
 * entered so far (the KL price lists) becomes KL's; the other companies start empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kl = DB::table('branches')->where('code', 'KL')->value('id') ?? DB::table('branches')->orderBy('id')->value('id');

        if (! Schema::hasColumn('uoms', 'branch_id')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // the product code is unique within a company now, not across them
        $indexes = collect(Schema::getIndexes('uoms'))->pluck('name')->unique(); // works on MySQL and the tests' SQLite

        if ($indexes->contains('uoms_code_unique')) {
            Schema::table('uoms', fn (Blueprint $table) => $table->dropUnique('uoms_code_unique'));
        }

        if (! $indexes->contains('uoms_branch_id_code_unique')) {
            Schema::table('uoms', fn (Blueprint $table) => $table->unique(['branch_id', 'code']));
        }

        if ($kl) {
            $products = DB::table('uoms')->whereNull('branch_id')->update(['branch_id' => $kl]);
            $charges = DB::table('drop_off_min_charges')->whereNull('branch_id')->update(['branch_id' => $kl]);
            echo "  {$products} products and {$charges} drop-off minimum charges given to KL; the other companies start empty.\n";
        }
    }

    public function down(): void
    {
        Schema::table('uoms', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'code']);
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('code');
        });
    }
};
