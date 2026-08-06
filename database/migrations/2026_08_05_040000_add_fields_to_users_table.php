<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_hq')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_hq');
            $table->string('phone')->nullable()->after('is_active');
            $table->foreignId('driver_id')->nullable()->after('phone');
            $table->foreignId('customer_id')->nullable()->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_hq', 'is_active', 'phone', 'driver_id', 'customer_id']);
        });
    }
};
