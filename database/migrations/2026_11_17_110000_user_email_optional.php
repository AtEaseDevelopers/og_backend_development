<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A user's email is optional (client, 17 Nov 2026): staff sign in with their Staff ID. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable()->change());
    }

    public function down(): void
    {
        // not reversed: users without an email would have none to restore
    }
};
