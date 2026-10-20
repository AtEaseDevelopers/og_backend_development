<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Data → Consignor / Consignee (user, 21 Oct 2026): saved consignors and consignees (name, address, PIC,
 * telephone) tagged to customers, picked on Create order to fill a consignor & consignee block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignor_consignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // consignor | consignee | both
            $table->string('type', 20)->default('both');
            $table->text('address')->nullable();
            $table->string('pic_name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            // the price list area (From for a consignor, To for a consignee)
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('consignor_consignee_customer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignor_consignee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['consignor_consignee_id', 'customer_id'], 'consignor_consignee_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignor_consignee_customer');
        Schema::dropIfExists('consignor_consignees');
    }
};
