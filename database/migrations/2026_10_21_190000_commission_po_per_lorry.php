<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The commission PO is per lorry (user, 21 Oct 2026): one PO per lorry per batch, listing what each of its drivers
 * earned. A slip / PO is the lorry's, so it no longer has one driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_slips', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });

        Schema::table('commission_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // left nullable (a lorry's PO has no single driver)
    }
};
