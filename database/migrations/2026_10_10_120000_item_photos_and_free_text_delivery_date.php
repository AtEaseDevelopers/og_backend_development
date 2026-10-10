<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create / Edit order:
 * - photos are uploaded per product: quotations.item_attachments = {product name: [{path, name, mime, …}]}
 *   (kept on the record, so re-pricing, which rebuilds the lines, keeps them);
 * - "Expected delivery date" is an optional free-text remark (no longer the CSN date): the date column
 *   becomes text (saved dates stay as they were, e.g. 2026-10-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'item_attachments')) {
                $table->json('item_attachments')->nullable()->after('attachments');
            }

            $table->string('expected_delivery_date', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        // text that is not a date cannot go back into a date column
        DB::table('quotations')
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', 'not regexp', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
            ->update(['expected_delivery_date' => null]);

        Schema::table('quotations', function (Blueprint $table) {
            $table->date('expected_delivery_date')->nullable()->change();

            if (Schema::hasColumn('quotations', 'item_attachments')) {
                $table->dropColumn('item_attachments');
            }
        });
    }
};
