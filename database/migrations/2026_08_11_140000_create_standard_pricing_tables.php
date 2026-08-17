<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->unique('name');
        });

        Schema::create('item_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['item_id', 'location_id']);
        });

        Schema::create('uom_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 10, 2)->default(1);
            $table->decimal('max_qty', 10, 2)->nullable();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['uom_id', 'location_id', 'min_qty'], 'uom_rate_tiers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_rate_tiers');
        Schema::dropIfExists('item_rates');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn('code');
        });
    }
};
