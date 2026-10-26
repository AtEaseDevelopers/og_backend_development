<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store a consignor brings the goods to is a Branch of the company (Master Data → Branches; user, 26 Oct 2026):
 * each branch gets a store row kept in step with it (stores.sa_location_id), so order records keep pointing at
 * stores. The old stores that are not a branch are deactivated (a record that used one keeps it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'sa_location_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->foreignId('sa_location_id')->nullable()->unique()->after('branch_id')->constrained('sa_locations')->nullOnDelete();
            });
        }

        foreach (DB::table('sa_locations')->orderBy('id')->get() as $branch) {
            if (DB::table('stores')->where('sa_location_id', $branch->id)->exists()) {
                continue;
            }

            // the company's only old store (its seeded "KL Store") becomes this branch's store; else a new row
            $existing = DB::table('stores')->whereNull('sa_location_id')->where('branch_id', $branch->branch_id)->orderBy('id')->get();
            $values = [
                'sa_location_id' => $branch->id,
                'company_id' => $branch->company_id,
                'branch_id' => $branch->branch_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => $branch->is_active,
                'updated_at' => now(),
            ];

            if ($existing->count() === 1 && DB::table('sa_locations')->where('branch_id', $branch->branch_id)->count() === 1) {
                $store = $existing->first();
                DB::table('stores')->where('id', $store->id)->update($values + ['address' => $branch->address ?: $store->address]);
                // the branch takes the store's address when it has none
                if (blank($branch->address) && filled($store->address)) {
                    DB::table('sa_locations')->where('id', $branch->id)->update(['address' => $store->address, 'updated_at' => now()]);
                }
            } else {
                DB::table('stores')->insert($values + ['address' => $branch->address, 'created_at' => now()]);
            }
        }

        DB::table('stores')->whereNull('sa_location_id')->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sa_location_id');
        });
    }
};
