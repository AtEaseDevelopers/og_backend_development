<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('pickup_maps_url')->nullable();
            $table->date('preferred_delivery_date')->nullable();
            $table->text('special_requirements')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('quotation_id')->nullable();
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('portal_enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('valid_until')->nullable();
            $table->string('pricing_source')->nullable(); // default|previous|formula|manual
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quotation_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('consignee_name')->nullable();
            $table->string('consignee_pic')->nullable();
            $table->string('consignee_phone')->nullable();
            $table->text('address');
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('google_maps_url')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_destination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('uom')->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('weight', 15, 3)->nullable();
            $table->string('dimensions')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->text('handling_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('ocr_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->json('extracted_data')->nullable();
            $table->string('status')->default('pending_review');
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credit_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
        });
        Schema::dropIfExists('credit_approval_requests');
        Schema::dropIfExists('ocr_uploads');
        Schema::dropIfExists('quotation_status_logs');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotation_destinations');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('portal_enquiries');
    }
};
