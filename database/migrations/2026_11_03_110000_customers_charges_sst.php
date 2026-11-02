<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Charge SST" on the customer (user, 3 Nov 2026): the customer's orders carry SST at the system rate (config og.sst_rate),
 * printed on the Order Quotation, Proforma, Cash Bill and Invoice. Off for every existing customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->boolean('charges_sst')->default(false)->after('tax_type'));
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('charges_sst'));
    }
};
