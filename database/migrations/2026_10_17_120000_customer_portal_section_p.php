<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First-draft customer portal: approval audit on portal access links, and mark existing users verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_user', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_user', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_user', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('customer_user', 'review_remarks')) {
                $table->string('review_remarks', 500)->nullable()->after('reviewed_at');
            }
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('customer_user', function (Blueprint $table) {
            if (Schema::hasColumn('customer_user', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['reviewed_at', 'review_remarks'] as $column) {
                if (Schema::hasColumn('customer_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
