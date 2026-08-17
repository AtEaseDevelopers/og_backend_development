<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chartered_lorries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('chartered_lorry_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chartered_lorry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['chartered_lorry_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chartered_lorry_rates');
        Schema::dropIfExists('chartered_lorries');
    }
};
