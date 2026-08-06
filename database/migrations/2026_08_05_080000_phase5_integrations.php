<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->foreignId('source_branch_id')->nullable()->after('id');
            $table->string('external_ref')->nullable()->after('document_id');
            $table->timestamp('synced_at')->nullable()->after('synced_by');
        });

        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->string('submission_mode')->default('manual')->after('status');
            $table->timestamp('email_sent_at')->nullable()->after('submitted_at');
            $table->string('buyer_token')->nullable()->unique()->after('buyer_info');
        });

        Schema::table('ocr_uploads', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('file_path');
            $table->foreignId('reviewed_by')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->timestamp('alerted_at')->nullable()->after('attachment_path');
            $table->string('status')->default('active')->after('alerted_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('einvoice_buyer_name')->nullable()->after('address');
            $table->string('einvoice_tin')->nullable()->after('einvoice_buyer_name');
            $table->string('einvoice_id_type')->nullable()->after('einvoice_tin');
            $table->string('einvoice_id_value')->nullable()->after('einvoice_id_type');
            $table->string('einvoice_address')->nullable()->after('einvoice_id_value');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_buyer_name', 'einvoice_tin', 'einvoice_id_type',
                'einvoice_id_value', 'einvoice_address',
            ]);
        });

        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['alerted_at', 'status']);
        });

        Schema::table('ocr_uploads', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });

        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->dropColumn(['submission_mode', 'email_sent_at', 'buyer_token']);
        });

        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropColumn(['source_branch_id', 'external_ref', 'synced_at']);
        });
    }
};
