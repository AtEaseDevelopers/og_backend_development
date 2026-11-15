<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The change history of a quotation (user, 16 Nov 2026): one row per save that changed something, with the fields
// changed, from which value to which value, who saved and when (App\Support\QuoteChangeLog). Shown in the History
// window of the Edit quotation page. Changes made before this table existed are not known.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quote_changes')) {
            return;
        }

        Schema::create('quote_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes');
            $table->timestamps();
            $table->index(['quote_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_changes');
    }
};
