<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff of each transfer code (user, 21 Oct 2026): set by admin on Master Data → Transfer Codes. A staff member
 * assigns the subsheets of their transfer codes to a lorry from the CSN page ("Assign subsheets to lorry").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_code_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transfer_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_code_user');
    }
};
