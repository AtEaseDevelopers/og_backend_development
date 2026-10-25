<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A staff account's own page settings (user, 25 Oct 2026): first the Master Data cards the user hides
 * (preferences.master_data_hidden).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('preferences')->nullable()->after('ordering_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
