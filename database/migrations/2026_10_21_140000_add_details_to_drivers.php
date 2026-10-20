<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The driver list's columns (user, 21 Oct 2026): the driver's emergency contact and address, the lorry group and
 * lorry company, the lorry (number, type, tonne) and the attendant (name, IC, phone, emergency contact, address).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('emergency_contact', 150)->nullable()->after('ic_number');
            $table->text('address')->nullable()->after('emergency_contact');
            $table->string('lorry_group_code', 50)->nullable()->after('address');
            $table->string('lorry_group', 150)->nullable()->after('lorry_group_code');
            $table->string('lorry_company_code', 50)->nullable()->after('lorry_group');
            $table->string('lorry_company', 150)->nullable()->after('lorry_company_code');
            $table->string('lorry_number', 30)->nullable()->after('lorry_company');
            $table->string('lorry_type', 50)->nullable()->after('lorry_number');
            $table->decimal('tonnage', 8, 2)->nullable()->after('lorry_type');
            $table->string('attendant_name', 150)->nullable()->after('tonnage');
            $table->string('attendant_ic', 30)->nullable()->after('attendant_name');
            $table->string('attendant_phone', 50)->nullable()->after('attendant_ic');
            $table->string('attendant_emergency_contact', 150)->nullable()->after('attendant_phone');
            $table->text('attendant_address')->nullable()->after('attendant_emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact', 'address', 'lorry_group_code', 'lorry_group', 'lorry_company_code', 'lorry_company',
                'lorry_number', 'lorry_type', 'tonnage', 'attendant_name', 'attendant_ic', 'attendant_phone',
                'attendant_emergency_contact', 'attendant_address',
            ]);
        });
    }
};
