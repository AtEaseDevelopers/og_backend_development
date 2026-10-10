<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CSN management: a subsheet is its own record under its CSN and can be created before any lorry (assigned
 * later, together with CSNs), so its job sheet / delivery order are empty until then. A subsheet's delivery
 * order is marked with delivery_orders.subsheet_id: a subsheet assigned before its CSN has no main delivery
 * order to hang under (parent_do_id), and must never be taken for the CSN's own delivery order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subsheets', function (Blueprint $table) {
            $table->unsignedBigInteger('job_sheet_id')->nullable()->change();
            $table->unsignedBigInteger('delivery_order_id')->nullable()->change();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_orders', 'subsheet_id')) {
                $table->foreignId('subsheet_id')->nullable()->after('parent_do_id')->constrained('subsheets')->nullOnDelete();
            }
        });

        // the delivery orders of the subsheets made so far
        DB::table('subsheets')->whereNotNull('delivery_order_id')->orderBy('id')->get(['id', 'delivery_order_id'])
            ->each(fn ($subsheet) => DB::table('delivery_orders')->where('id', $subsheet->delivery_order_id)->update(['subsheet_id' => $subsheet->id]));
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_orders', 'subsheet_id')) {
                $table->dropConstrainedForeignId('subsheet_id');
            }
        });

        // a subsheet without a lorry cannot stay once the columns are required again
        DB::table('subsheets')->whereNull('job_sheet_id')->orWhereNull('delivery_order_id')->delete();

        Schema::table('subsheets', function (Blueprint $table) {
            $table->unsignedBigInteger('job_sheet_id')->nullable(false)->change();
            $table->unsignedBigInteger('delivery_order_id')->nullable(false)->change();
        });
    }
};
