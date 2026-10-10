<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores (master data): a branch has many stores where a consignor brings goods (Create order → Consignor →
 * Store). Each store keeps its address (the pickup location), PIC, contact number and price-list "From"
 * location. Order records point to the store they used (quotations.store_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('code', 30)->nullable();
                $table->string('name');
                $table->text('address')->nullable();
                $table->string('pic_name')->nullable();
                $table->string('pic_phone', 50)->nullable();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['branch_id', 'is_active']);
            });
        }

        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'store_id')) {
                $table->foreignId('store_id')->nullable()->after('store_branch_id')->constrained('stores')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'store_id')) {
                $table->dropConstrainedForeignId('store_id');
            }
        });

        Schema::dropIfExists('stores');
    }
};
