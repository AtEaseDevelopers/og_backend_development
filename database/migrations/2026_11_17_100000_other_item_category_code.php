<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Other Items (user, 16 Nov 2026): a category and a code, both free text. On an Other Invoice the category is picked
// first, then the item of that category.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('other_items', function (Blueprint $table) {
            if (! Schema::hasColumn('other_items', 'category')) {
                $table->string('category', 150)->nullable();
            }
            if (! Schema::hasColumn('other_items', 'code')) {
                $table->string('code', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('other_items', function (Blueprint $table) {
            foreach (['category', 'code'] as $column) {
                if (Schema::hasColumn('other_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
