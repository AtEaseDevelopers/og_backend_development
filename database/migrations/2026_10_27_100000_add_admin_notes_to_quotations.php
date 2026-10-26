<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order form's Instructions became two notes (user, 27 Oct 2026): Driver notes (the record's instructions, printed
 * on the CSN) and Admin notes (this column: internal, never on the CSN).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotations', 'admin_notes')) {
            Schema::table('quotations', fn (Blueprint $table) => $table->text('admin_notes')->nullable()->after('notes'));
        }
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('admin_notes'));
    }
};
