<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A CSN can be moved to another company (user, 28 Oct 2026): that company sees it in its CSN list and may only
 * assign it to a lorry; the owning company keeps it, marked with the company it went to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->foreignId('moved_to_company_id')->nullable()->after('company_id')->constrained('companies')->nullOnDelete();
            $table->timestamp('moved_at')->nullable()->after('moved_to_company_id');
            $table->foreignId('moved_by')->nullable()->after('moved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moved_by');
            $table->dropColumn('moved_at');
            $table->dropConstrainedForeignId('moved_to_company_id');
        });
    }
};
