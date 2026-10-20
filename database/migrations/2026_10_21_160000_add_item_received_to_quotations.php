<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Store order (the customer brings the goods to the store, user 21 Oct 2026): paid or released, it stays at
 * Payment verification / Pending item until someone confirms the goods are at the store ("Ready for CSN"); its CSN
 * is created then. When and by whom that was confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('item_received_at')->nullable();
            $table->foreignId('item_received_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_received_by');
            $table->dropColumn('item_received_at');
        });
    }
};
