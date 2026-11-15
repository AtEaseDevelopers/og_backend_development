<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The client's "Customer Master Data V2" (user, 16 Nov 2026): two fields the customer did not have — the account
// opening date and a mailing address (NOTES). And the document's Debtor Type (Cash / Credit / COD) is the customer's
// order type: a customer that had none takes it from what it was — a credit customer is Credit, the others Cash.
// No other column is dropped: the fields taken off the form keep their stored values.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'account_opening_date')) {
                $table->date('account_opening_date')->nullable();
            }
            if (! Schema::hasColumn('customers', 'mailing_address')) {
                $table->text('mailing_address')->nullable();
            }
        });

        DB::table('customers')
            ->where(fn ($query) => $query->whereNull('default_order_type')->orWhereNotIn('default_order_type', ['cash', 'term', 'cod']))
            ->where('is_credit', true)
            ->update(['default_order_type' => 'term']);
        DB::table('customers')
            ->where(fn ($query) => $query->whereNull('default_order_type')->orWhereNotIn('default_order_type', ['cash', 'term', 'cod']))
            ->update(['default_order_type' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['account_opening_date', 'mailing_address'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
