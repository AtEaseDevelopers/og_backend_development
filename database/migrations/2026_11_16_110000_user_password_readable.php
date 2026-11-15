<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Data -> Users shows a user's current password to an admin (user, 16 Nov 2026). The password itself stays
// hashed; a second copy is kept encrypted with the application key (users.password_readable) so it can be shown. It is
// filled when a password is set on the Users page from now on; a password set before, or changed anywhere else (the
// portal's reset link, the driver page), has no copy and shows as "not recorded" until it is set again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'password_readable')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('password_readable')->nullable()->after('password'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_readable')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_readable'));
        }
    }
};
