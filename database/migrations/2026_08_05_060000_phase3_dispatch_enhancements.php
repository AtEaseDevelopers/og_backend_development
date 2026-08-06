<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subsheets', function (Blueprint $table) {
            $table->decimal('psi_amount', 15, 2)->default(0)->after('handover_status');
            $table->decimal('pso_amount', 15, 2)->default(0)->after('psi_amount');
            $table->foreignId('profit_sharing_transaction_id')->nullable()->after('pso_amount');
            $table->string('task_type')->default('transfer')->after('transfer_code'); // transfer|incoming_psi
            $table->text('notes')->nullable()->after('task_type');
        });

        Schema::table('break_bulks', function (Blueprint $table) {
            $table->foreignId('requested_by_driver_id')->nullable()->after('original_lorry_id');
            $table->foreignId('created_by')->nullable()->after('requested_by_driver_id');
            $table->text('revoke_reason')->nullable()->after('status');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('job_sheets', function (Blueprint $table) {
            $table->boolean('is_shared_dispatch')->default(false)->after('status');
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('incomplete_delivery_alerts', function (Blueprint $table) {
            $table->id();
            $table->date('alert_date');
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open'); // open|acknowledged|resolved
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['alert_date', 'delivery_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomplete_delivery_alerts');
        Schema::dropIfExists('notifications');

        Schema::table('job_sheets', function (Blueprint $table) {
            $table->dropColumn('is_shared_dispatch');
        });

        Schema::table('break_bulks', function (Blueprint $table) {
            $table->dropColumn([
                'requested_by_driver_id', 'created_by', 'revoke_reason',
                'released_at', 'collected_at', 'completed_at',
            ]);
        });

        Schema::table('subsheets', function (Blueprint $table) {
            $table->dropColumn([
                'psi_amount', 'pso_amount', 'profit_sharing_transaction_id',
                'task_type', 'notes',
            ]);
        });
    }
};
