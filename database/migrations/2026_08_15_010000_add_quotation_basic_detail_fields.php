<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->date('expected_delivery_date')->nullable()->after('quoted_at');
            $table->decimal('myr_to_sgd_rate', 12, 6)->default(0.320718)->after('expected_delivery_date');
            $table->decimal('sgd_to_myr_rate', 12, 6)->default(3.247000)->after('myr_to_sgd_rate');
            $table->foreignId('from_location_id')->nullable()->after('sgd_to_myr_rate')->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('locations')->nullOnDelete();
            $table->string('consignor_brn')->nullable()->after('to_location_id');
            $table->text('pickup_location')->nullable()->after('consignor_brn');
            $table->string('consignee_name')->nullable()->after('pickup_location');
            $table->string('consignee_brn')->nullable()->after('consignee_name');
            $table->text('consignee_address')->nullable()->after('consignee_brn');
            $table->text('drop_off_location')->nullable()->after('consignee_address');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropColumn([
                'expected_delivery_date',
                'myr_to_sgd_rate',
                'sgd_to_myr_rate',
                'consignor_brn',
                'pickup_location',
                'consignee_name',
                'consignee_brn',
                'consignee_address',
                'drop_off_location',
            ]);
        });
    }
};
