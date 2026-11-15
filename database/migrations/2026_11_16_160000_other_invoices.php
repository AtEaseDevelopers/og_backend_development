<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Other Invoice (client + user, 16 Nov 2026): an invoice for charges that are not from a consignment note (diesel,
// toll reload, rentals …). Its items come from a new Master Data list, Other Items (a description only); the user
// keys the description and the amount of each line. Number: OIYYMM##### (OI261200001). No AutoCount link yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('other_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->boolean('charges_sst')->default(false);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('other_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('other_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('other_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_invoice_lines');
        Schema::dropIfExists('other_invoices');
        Schema::dropIfExists('other_items');
    }
};
