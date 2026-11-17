<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Data -> Users (user, 17 Nov 2026): a super admin allows or disables the Register Link user by user.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'register_link_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('register_link_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'register_link_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('register_link_enabled');
            });
        }
    }
};
