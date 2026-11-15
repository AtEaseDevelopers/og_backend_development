<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Data -> Area (user, 13 Nov 2026): an area without price rows can be deleted; it is kept in the table
// (deleted_at) so the orders and CSNs that used it still show its name.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('locations', 'deleted_at')) {
            Schema::table('locations', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('locations', 'deleted_at')) {
            Schema::table('locations', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
