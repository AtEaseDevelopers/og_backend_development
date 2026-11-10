<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O&G's document numbers (client's list, user 11 Nov 2026): an invoice (IVYYMM#####) and a refund note (RN########)
 * have no company in the number, each company keeps its own running number and the letterhead tells them apart, so
 * the same number may exist once in every company: unique per company instead of across companies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_number_unique');
            $table->unique(['company_id', 'number'], 'invoices_company_number_unique');
        });

        Schema::table('refund_notes', function (Blueprint $table): void {
            $table->dropUnique('refund_notes_number_unique');
            $table->unique(['company_id', 'number'], 'refund_notes_company_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_company_number_unique');
            $table->unique('number', 'invoices_number_unique');
        });

        Schema::table('refund_notes', function (Blueprint $table): void {
            $table->dropUnique('refund_notes_company_number_unique');
            $table->unique('number', 'refund_notes_number_unique');
        });
    }
};
