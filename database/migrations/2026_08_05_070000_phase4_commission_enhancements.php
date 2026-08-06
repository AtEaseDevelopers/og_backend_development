<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returned_csns', function (Blueprint $table) {
            $table->string('scan_method')->default('manual')->after('received_by'); // qr|manual
            $table->string('status')->default('received')->after('scan_method');
            $table->text('remarks')->nullable()->after('returned_at');
        });

        Schema::table('missing_csn_logs', function (Blueprint $table) {
            $table->foreignId('source_branch_id')->nullable()->after('consignment_note_id');
            $table->foreignId('delivery_order_id')->nullable()->after('source_branch_id');
            $table->foreignId('returned_csn_id')->nullable()->after('investigation_status');
            $table->timestamp('resolved_at')->nullable()->after('returned_csn_id');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at');
        });

        Schema::table('commission_batches', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->text('notes')->nullable()->after('confirmed_at');
            $table->unique(['source_branch_id', 'month']);
        });

        Schema::table('commission_slips', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('commission_line_items', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('consignment_note_id');
            $table->foreignId('lorry_id')->nullable()->after('driver_id');
            $table->decimal('split_percent', 8, 2)->default(100)->after('amount');
            $table->string('line_type')->default('delivery')->after('split_percent');
            // delivery|failed|psi|pso|break_bulk|carry_forward
            $table->text('notes')->nullable()->after('is_carry_forward');
        });

        Schema::table('commission_rules', function (Blueprint $table) {
            $table->decimal('rate_percent', 8, 2)->default(10)->after('split_type');
            $table->foreignId('source_branch_id')->nullable()->after('id');
        });

        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->string('return_status')->default('not_required')->after('status');
            // not_required|pending_return|returned|missing
        });
    }

    public function down(): void
    {
        Schema::table('consignment_notes', function (Blueprint $table) {
            $table->dropColumn('return_status');
        });

        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropColumn(['rate_percent', 'source_branch_id']);
        });

        Schema::table('commission_line_items', function (Blueprint $table) {
            $table->dropColumn(['driver_id', 'lorry_id', 'split_percent', 'line_type', 'notes']);
        });

        Schema::table('commission_slips', function (Blueprint $table) {
            $table->dropColumn(['number', 'notes']);
        });

        Schema::table('commission_batches', function (Blueprint $table) {
            $table->dropUnique(['source_branch_id', 'month']);
            $table->dropColumn(['number', 'notes']);
        });

        Schema::table('missing_csn_logs', function (Blueprint $table) {
            $table->dropColumn([
                'source_branch_id', 'delivery_order_id', 'returned_csn_id',
                'resolved_at', 'resolved_by',
            ]);
        });

        Schema::table('returned_csns', function (Blueprint $table) {
            $table->dropColumn(['scan_method', 'status', 'remarks']);
        });
    }
};
