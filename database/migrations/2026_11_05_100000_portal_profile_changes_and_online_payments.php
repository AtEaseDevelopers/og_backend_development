<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profile_changes')) {
            Schema::create('customer_profile_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('section', 30);
                $table->string('action', 20)->default('update');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('changes');
                $table->string('status', 20);
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('review_remarks', 500)->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('online_payments')) {
            Schema::create('online_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 40)->unique();
                $table->decimal('amount', 12, 2);
                $table->string('gateway', 30);
                $table->string('status', 20)->default('initiated');
                $table->string('gateway_txn_id', 100)->nullable();
                $table->foreignId('payment_submission_id')->nullable()->constrained()->nullOnDelete();
                $table->string('failure_reason', 255)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
        Schema::dropIfExists('customer_profile_changes');
    }
};
