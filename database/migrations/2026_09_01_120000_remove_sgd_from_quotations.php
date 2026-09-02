<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['myr_to_sgd_rate', 'sgd_to_myr_rate']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('myr_to_sgd_rate', 12, 6)->default(0.320718)->after('expected_delivery_date');
            $table->decimal('sgd_to_myr_rate', 12, 6)->default(3.247000)->after('myr_to_sgd_rate');
        });
    }
};
