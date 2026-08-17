<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('title')->nullable()->after('valid_until');
            $table->boolean('is_active')->default(true)->after('title');
            $table->date('quoted_at')->nullable()->after('is_active');
            $table->text('customer_address')->nullable()->after('quoted_at');
            $table->string('attention')->nullable()->after('customer_address');
            $table->string('customer_fax')->nullable()->after('attention');
            $table->string('customer_phone_alt')->nullable()->after('customer_fax');
            $table->string('issued_by_name')->nullable()->after('customer_phone_alt');
            $table->string('terms_of_payment')->nullable()->after('issued_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'is_active',
                'quoted_at',
                'customer_address',
                'attention',
                'customer_fax',
                'customer_phone_alt',
                'issued_by_name',
                'terms_of_payment',
            ]);
        });
    }
};
