<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment can carry several slips / receipts. receipt_path / slip_path keep the first file (read by the
 * customer portal, the payment submission screen and AutoCount); the new JSON lists hold every file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_submissions', 'receipt_paths')) {
                $table->json('receipt_paths')->nullable()->after('receipt_path');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'slip_paths')) {
                $table->json('slip_paths')->nullable()->after('slip_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_submissions', 'receipt_paths')) {
                $table->dropColumn('receipt_paths');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'slip_paths')) {
                $table->dropColumn('slip_paths');
            }
        });
    }
};
