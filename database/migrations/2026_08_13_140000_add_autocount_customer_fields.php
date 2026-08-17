<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('control_account')->nullable()->after('code');
            $table->string('debtor_type')->nullable()->after('control_account');
            $table->boolean('is_group_company')->default(false)->after('debtor_type');
            $table->string('area')->nullable()->after('address');
            $table->string('fax')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->string('attention')->nullable()->after('website');
            $table->string('business_nature')->nullable()->after('attention');
            $table->foreignId('salesperson_id')->nullable()->after('business_nature')->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('MYR')->after('salesperson_id');
            $table->string('statement_type')->default('open_item')->after('currency');
            $table->string('aging_on')->default('due_date')->after('statement_type');
            $table->string('credit_control')->default('controlled_by_credit_term')->after('aging_on');
            $table->decimal('credit_overdue_limit', 15, 2)->nullable()->after('credit_limit');
            $table->string('credit_control_scope')->default('all_documents')->after('credit_overdue_limit');
            $table->string('sales_tax_exemption_no')->nullable()->after('credit_control_scope');
            $table->date('sales_tax_exemption_expiry')->nullable()->after('sales_tax_exemption_no');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('sales_tax_exemption_expiry');
            $table->string('tax_type')->nullable()->after('discount_percent');
            $table->string('price_category')->nullable()->after('tax_type');
            $table->string('account_group')->nullable()->after('price_category');
            $table->text('notes')->nullable()->after('account_group');
            $table->string('sst_registration_no')->nullable()->after('tin');
            $table->string('msic_code')->nullable()->after('sst_registration_no');
            $table->string('business_type')->nullable()->after('msic_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropColumn([
                'control_account', 'debtor_type', 'is_group_company', 'area', 'fax',
                'website', 'attention', 'business_nature', 'currency', 'statement_type',
                'aging_on', 'credit_control', 'credit_overdue_limit', 'credit_control_scope',
                'sales_tax_exemption_no', 'sales_tax_exemption_expiry', 'discount_percent',
                'tax_type', 'price_category', 'account_group', 'notes',
                'sst_registration_no', 'msic_code', 'business_type',
            ]);
        });
    }
};
