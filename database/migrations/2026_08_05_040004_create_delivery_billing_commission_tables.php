<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proofs_of_delivery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->json('photo_paths')->nullable();
            $table->string('pod_document_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('cod_amount_collected', 15, 2)->nullable();
            $table->string('cod_payment_method')->nullable();
            $table->text('remarks')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('failed_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('remarks')->nullable();
            $table->json('photo_paths')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('reassignment_option')->nullable(); // standard|duplicate
            $table->foreignId('replacement_do_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('break_bulks', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('delivery_order_id')->constrained();
            $table->foreignId('job_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consignment_note_id')->constrained();
            $table->foreignId('original_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('original_lorry_id')->nullable()->constrained('lorries')->nullOnDelete();
            $table->foreignId('replacement_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('replacement_lorry_id')->nullable()->constrained('lorries')->nullOnDelete();
            $table->foreignId('subcontractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('reason')->nullable();
            $table->string('handover_status')->default('pending');
            $table->string('status')->default('active');
            $table->json('photo_paths')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('returned_csns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_note_id')->constrained();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('returned_by_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_signed')->default(false);
            $table->boolean('is_stamped')->default(false);
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('missing_csn_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_note_id')->constrained();
            $table->string('status')->default('pending_return');
            $table->timestamp('marked_missing_at')->nullable();
            $table->text('follow_up_remarks')->nullable();
            $table->string('investigation_status')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('customer_id')->constrained();
            $table->string('type')->default('cash_bill'); // cash_bill|term|forfeit|additional
            $table->string('billing_month', 7)->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('rounding_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('autocount_sync_status')->default('not_synced');
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consignment_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('status')->default('completed');
            $table->string('slip_path')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type')->default('official'); // official|ar|cod
            $table->string('autocount_sync_status')->default('not_synced');
            $table->timestamps();
        });

        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();
            $table->string('status')->default('issued');
            $table->string('autocount_sync_status')->default('not_synced');
            $table->timestamps();
        });

        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('customer_id')->constrained();
            $table->date('statement_date');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('einvoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('uuid')->nullable();
            $table->string('validated_pdf_path')->nullable();
            $table->json('buyer_info')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration'); // autocount|myinvois|payment
            $table->string('document_type');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('status');
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('synced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commission_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->string('month', 7);
            $table->date('cutoff_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('driver_id')->constrained();
            $table->foreignId('lorry_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('system_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->decimal('psi_amount', 15, 2)->default(0);
            $table->decimal('pso_amount', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('commission_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_slip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consignment_note_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_eligible')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->string('hidden_reason')->nullable();
            $table->boolean('is_carry_forward')->default(false);
            $table->timestamps();
        });

        Schema::create('commission_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_slip_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commission_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('pi_number')->nullable();
            $table->foreignId('commission_slip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('driver_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('generated');
            $table->string('autocount_sync_status')->default('not_synced');
            $table->timestamps();
        });

        Schema::create('customer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->timestamps();
            $table->unique(['customer_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::table('driver_check_ins', function (Blueprint $table) {
            $table->foreign('job_sheet_id')->references('id')->on('job_sheets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('driver_check_ins', function (Blueprint $table) {
            $table->dropForeign(['job_sheet_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['customer_id']);
        });
        Schema::dropIfExists('customer_user');
        Schema::dropIfExists('commission_purchase_orders');
        Schema::dropIfExists('commission_adjustments');
        Schema::dropIfExists('commission_line_items');
        Schema::dropIfExists('commission_slips');
        Schema::dropIfExists('commission_batches');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('einvoice_submissions');
        Schema::dropIfExists('statements');
        Schema::dropIfExists('payment_vouchers');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('missing_csn_logs');
        Schema::dropIfExists('returned_csns');
        Schema::dropIfExists('break_bulks');
        Schema::dropIfExists('failed_deliveries');
        Schema::dropIfExists('proofs_of_delivery');
    }
};
