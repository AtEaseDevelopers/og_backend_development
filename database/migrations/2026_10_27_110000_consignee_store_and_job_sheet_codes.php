<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consignee "Store" (user, 27 Oct 2026): the goods go to one of the company's stores (a branch) and the customer
 * collects them there — quotations.consignee_store_id. When such an order becomes a CSN, a subsheet with transfer
 * code O (own collection) is created. The client's job sheet codes (A, C, E, J, K, L, O, P, S, T) are entered as
 * transfer codes so a subsheet can refer to them.
 */
return new class extends Migration
{
    private const JOB_SHEET_CODES = [
        'A' => 'From Johor / Melaka / Negeri Sembilan to KL',
        'C' => 'JB to JB Local (O&G JB assign lorry)',
        'E' => 'From KL to Perniagaan Khoo Kow EE (used when the orders go to a shopping centre)',
        'J' => 'From KL to O&G KL (JB Branch) to transfer to small lorry',
        'K' => 'From KL to Sikaram Agency (including transfer to small lorry and own collection)',
        'L' => 'Local trip within the same state',
        'O' => 'From KL to O&G KL (JB Branch) to own collection',
        'P' => 'From KL to Muar Store (including transfer to small lorry and own collection)',
        'S' => 'Orders from Syarikat Pengangkutan (Penang–Selangor)',
        'T' => 'Large lorry to large lorry (normally used when a lorry breaks down)',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('quotations', 'consignee_store_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->foreignId('consignee_store_id')->nullable()->after('store_id')->constrained('stores')->nullOnDelete();
            });
        }

        foreach (self::JOB_SHEET_CODES as $code => $name) {
            if (! DB::table('transfer_codes')->where('code', $code)->exists()) {
                DB::table('transfer_codes')->insert(['code' => $code, 'name' => $name, 'type' => 'transfer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropConstrainedForeignId('consignee_store_id'));
    }
};
