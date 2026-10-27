<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores are master data again (user, 28 Oct 2026): a branch can have several stores (Master Data → Stores), so the
 * one-store-per-branch rule (unique stores.sa_location_id) goes. The store each branch got on 26 Oct stays as its
 * first store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['sa_location_id']);
            $table->dropUnique(['sa_location_id']);
            $table->foreign('sa_location_id')->references('id')->on('sa_locations')->nullOnDelete();
            $table->index('sa_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['sa_location_id']);
            $table->dropIndex(['sa_location_id']);
            $table->unique('sa_location_id');
            $table->foreign('sa_location_id')->references('id')->on('sa_locations')->nullOnDelete();
        });
    }
};
