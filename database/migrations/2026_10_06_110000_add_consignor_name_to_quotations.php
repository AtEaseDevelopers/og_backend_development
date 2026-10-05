<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each consignor & consignee record of an order can name its own consignor (pickup party);
 * the customer (billing / ownership) stays the same across the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->string('consignor_name')->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn('consignor_name');
        });
    }
};
