<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up fixes for sections A–J:
 *  - service type (Pick Up / Store) captured on the order form and the order record (H)
 *  - price override reason + audit of overridden master prices (D)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable()->after('order_type');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable()->after('order_type');
            $table->text('pricing_override_reason')->nullable()->after('pricing_source');
            $table->json('price_overrides')->nullable()->after('pricing_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['service_type', 'pricing_override_reason', 'price_overrides']));
        Schema::table('portal_enquiries', fn (Blueprint $table) => $table->dropColumn('service_type'));
    }
};
