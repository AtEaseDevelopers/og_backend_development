<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->date('issued_at')->nullable()->after('billing_type');
            $table->string('customer_reference')->nullable()->after('customer_tin');
            $table->text('remarks')->nullable()->after('delivery_city');
        });

        Schema::table('csn_lines', function (Blueprint $table) {
            $table->text('handling_notes')->nullable()->after('dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('csn_lines', function (Blueprint $table) {
            $table->dropColumn('handling_notes');
        });

        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->dropColumn(['issued_at', 'customer_reference', 'remarks']);
        });
    }
};
