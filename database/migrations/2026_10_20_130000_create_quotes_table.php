<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations (Operations → Quotation, user 20 Oct 2026), on their own table: a customer or lead, the order's
 * section 1 and its consignor & consignee blocks with products and quoted prices (the same shape as Create order).
 * Pending confirmation until "Create order" makes the order from it (status ordered, order_enquiry_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->date('quote_date');
            $table->string('status', 20)->default('pending');
            $table->json('form')->nullable();
            $table->json('pairs')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('order_enquiry_id')->nullable()->constrained('portal_enquiries')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'quote_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
