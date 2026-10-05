<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Structured details for status-log entries (e.g. the exact prices offered to the customer). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_status_logs', function (Blueprint $table): void {
            $table->json('meta')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_status_logs', function (Blueprint $table): void {
            $table->dropColumn('meta');
        });
    }
};
