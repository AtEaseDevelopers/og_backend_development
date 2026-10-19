<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads (master data, user 20 Oct 2026): a possible customer's name, PIC, contact number, email, billing address and
 * address. A lead is quoted on Create quotation; when its quotation becomes an order it becomes a customer
 * (customer_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pic_name')->nullable();
            $table->string('contact', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
