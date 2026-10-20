<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accept by customer no longer creates an order (user, 21 Oct 2026): the quotation becomes Accepted and its prices
 * the customer's special prices. When it was accepted is kept; quotations already opened as an order count as
 * accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
        });

        DB::table('quotes')->where('status', 'ordered')->update([
            'status' => 'accepted',
            'accepted_at' => DB::raw('ordered_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('quotes')->where('status', 'accepted')->update(['status' => 'ordered']);

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
