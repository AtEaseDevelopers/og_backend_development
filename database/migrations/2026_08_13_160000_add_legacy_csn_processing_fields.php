<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->string('do_number')->nullable()->after('customer_reference');
            $table->string('job_no')->nullable()->after('do_number');
            $table->date('job_date')->nullable()->after('job_no');
            $table->foreignId('from_location_id')->nullable()->after('job_date')->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('locations')->nullOnDelete();
            $table->string('customer_phone')->nullable()->after('customer_tin');
            $table->string('consignor_name')->nullable()->after('consignor_address');
            $table->string('consignor_phone')->nullable()->after('consignor_name');
            $table->string('profit_sharing_period', 7)->nullable()->after('remarks');
            $table->string('ps_job_no')->nullable()->after('profit_sharing_period');
            $table->date('ps_job_date')->nullable()->after('ps_job_no');
            $table->string('gl_account')->nullable()->after('ps_job_date');
            $table->string('gl_account_name')->nullable()->after('gl_account');
            $table->string('tax_code')->nullable()->after('gl_account_name');
            $table->string('tax_code_name')->nullable()->after('tax_code');
            $table->json('other_do_numbers')->nullable()->after('tax_code_name');
            $table->string('marking')->nullable()->after('other_do_numbers');
            $table->decimal('transport_charges', 15, 2)->default(0)->after('marking');
            $table->decimal('master_charges', 15, 2)->default(0)->after('transport_charges');
            $table->decimal('profit_sharing_amount', 15, 2)->default(0)->after('master_charges');
            $table->decimal('expenses', 15, 2)->default(0)->after('profit_sharing_amount');
            $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax_rate', 5, 2)->default(6)->after('tax_amount');
            $table->string('cost_center')->nullable()->after('tax_rate');
            $table->boolean('is_taxable')->default(true)->after('cost_center');
            $table->boolean('advance_taken')->default(false)->after('is_taxable');
            $table->boolean('issue_invoice')->default(true)->after('advance_taken');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropColumn([
                'do_number',
                'job_no',
                'job_date',
                'customer_phone',
                'consignor_name',
                'consignor_phone',
                'profit_sharing_period',
                'ps_job_no',
                'ps_job_date',
                'gl_account',
                'gl_account_name',
                'tax_code',
                'tax_code_name',
                'other_do_numbers',
                'marking',
                'transport_charges',
                'master_charges',
                'profit_sharing_amount',
                'expenses',
                'discount',
                'tax_rate',
                'cost_center',
                'is_taxable',
                'advance_taken',
                'issue_invoice',
            ]);
        });
    }
};
