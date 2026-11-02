<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer job sheets (client's BA, 3 Nov 2026): a job sheet is main (a lorry's trip) or transfer (lorry A's code-J
 * legs, opened as lorry A's job number + J, with no lorry until someone picks it up). Existing job sheets are main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sheets', function (Blueprint $table) {
            $table->foreignId('lorry_id')->nullable()->change();
            $table->string('kind', 20)->default('main')->after('number');
            $table->foreignId('parent_job_sheet_id')->nullable()->after('kind')->constrained('job_sheets')->nullOnDelete();
            $table->string('transfer_code', 20)->nullable()->after('parent_job_sheet_id');
            $table->index(['kind', 'parent_job_sheet_id', 'transfer_code'], 'job_sheets_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('job_sheets', function (Blueprint $table) {
            $table->dropIndex('job_sheets_transfer_idx');
            $table->dropConstrainedForeignId('parent_job_sheet_id');
            $table->dropColumn(['kind', 'transfer_code']);
        });
    }
};
