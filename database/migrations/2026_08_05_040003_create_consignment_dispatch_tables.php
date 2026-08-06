<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_destination_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->string('billing_type')->default('cash_bill'); // cash_bill|cod|term
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');
            // snapshots
            $table->string('customer_name');
            $table->string('customer_brn')->nullable();
            $table->string('customer_tin')->nullable();
            $table->text('consignor_address')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('consignee_pic')->nullable();
            $table->string('consignee_phone')->nullable();
            $table->text('delivery_address');
            $table->string('delivery_postcode')->nullable();
            $table->string('delivery_state')->nullable();
            $table->string('delivery_city')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('storekeeper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('qr_token')->nullable()->unique();
            $table->string('tracking_token')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('csn_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_note_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->string('uom')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('weight', 15, 3)->nullable();
            $table->string('dimensions')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('job_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('operating_branch_id')->constrained('branches');
            $table->foreignId('lorry_id')->constrained();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->date('operating_date');
            $table->string('status')->default('draft'); // draft|in_transit|completed
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->unique(['lorry_id', 'operating_date'], 'job_sheet_lorry_date');
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('consignment_note_id')->constrained();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('job_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lorry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('assigned'); // assigned|in_transit|delivered|failed|transferred|reassigned|cancelled
            $table->string('tracking_token')->nullable()->unique();
            $table->foreignId('parent_do_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->boolean('is_duplicate')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_sheet_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('route_group')->nullable();
            $table->timestamps();
            $table->unique(['job_sheet_id', 'delivery_order_id']);
        });

        Schema::create('subsheets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('job_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_note_id')->constrained();
            $table->string('transfer_code')->nullable();
            $table->foreignId('main_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('main_lorry_id')->nullable()->constrained('lorries')->nullOnDelete();
            $table->foreignId('sub_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('sub_lorry_id')->nullable()->constrained('lorries')->nullOnDelete();
            $table->foreignId('subcontractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('segment_route')->nullable();
            $table->string('handover_status')->default('pending');
            $table->timestamps();
        });

        Schema::create('job_sheet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_job_sheet_id')->constrained('job_sheets');
            $table->foreignId('to_job_sheet_id')->constrained('job_sheets');
            $table->text('reason')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('profit_sharing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('delivery_order_id')->constrained();
            $table->foreignId('consignment_note_id')->constrained();
            $table->foreignId('assisting_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('main_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->decimal('psi_amount', 15, 2)->default(0);
            $table->decimal('pso_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('csn_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_note_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->string('refund_method')->nullable();
            $table->string('refund_reference')->nullable();
            $table->string('refund_status')->nullable();
            $table->boolean('forfeit_invoice')->default(false);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('consignment_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('issued');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
        Schema::dropIfExists('csn_cancellations');
        Schema::dropIfExists('profit_sharing_transactions');
        Schema::dropIfExists('job_sheet_transfers');
        Schema::dropIfExists('subsheets');
        Schema::dropIfExists('job_sheet_tasks');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('job_sheets');
        Schema::dropIfExists('csn_lines');
        Schema::dropIfExists('consignment_notes');
    }
};
