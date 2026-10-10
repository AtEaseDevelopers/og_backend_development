<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create order → Customer: the customer's person in charge (quotations.attention) and their contact number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'customer_pic_phone')) {
                $table->string('customer_pic_phone', 50)->nullable()->after('attention');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'customer_pic_phone')) {
                $table->dropColumn('customer_pic_phone');
            }
        });
    }
};
