<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('file_path');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('delivery_order_id')->nullable()->after('invoice_id');
            $table->foreignId('driver_id')->nullable()->after('delivery_order_id');
            $table->decimal('expected_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('shortage_amount', 15, 2)->nullable()->after('expected_amount');
            $table->string('reconciliation_status')->nullable()->after('status');
            $table->text('remarks')->nullable()->after('slip_path');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('consignment_note_id')->nullable()->after('customer_id');
        });

        Schema::table('credit_approval_requests', function (Blueprint $table) {
            $table->decimal('requested_amount', 15, 2)->nullable()->after('reason');
            $table->json('trigger_details')->nullable()->after('requested_amount');
        });

        Schema::create('cod_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('driver_id')->constrained('drivers');
            $table->date('reconciliation_date');
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('returned_amount', 15, 2)->default(0);
            $table->decimal('shortage_amount', 15, 2)->default(0);
            $table->string('status')->default('open');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_reconciliations');

        Schema::table('credit_approval_requests', function (Blueprint $table) {
            $table->dropColumn(['requested_amount', 'trigger_details']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('consignment_note_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_order_id', 'driver_id', 'expected_amount',
                'shortage_amount', 'reconciliation_status', 'remarks',
            ]);
        });

        Schema::table('statements', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
