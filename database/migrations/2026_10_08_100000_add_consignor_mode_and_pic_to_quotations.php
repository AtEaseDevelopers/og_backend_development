<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create / Edit order: the consignor is either picked up (service_type = pickup) or brought to an
 * O&G store (service_type = store, store_branch_id = the branch). Each side of a record has a person
 * in charge and a contact number instead of a company number. Additive and nullable only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignId('store_branch_id')->nullable()->after('service_type')->constrained('branches')->nullOnDelete();
            $table->string('consignor_pic_name')->nullable()->after('consignor_brn');
            $table->string('consignor_pic_phone', 50)->nullable()->after('consignor_pic_name');
            $table->string('consignee_pic_name')->nullable()->after('consignee_brn');
            $table->string('consignee_pic_phone', 50)->nullable()->after('consignee_pic_name');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_branch_id');
            $table->dropColumn(['consignor_pic_name', 'consignor_pic_phone', 'consignee_pic_name', 'consignee_pic_phone']);
        });
    }
};
