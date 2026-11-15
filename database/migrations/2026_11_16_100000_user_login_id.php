<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Staff sign in to the admin panel with a User ID, not their email (user, 16 Nov 2026). Every existing user gets a
// starting User ID: "admin" for the first super admin, otherwise the part of their email before the @ (a number is
// added when two would be the same). An admin sees and changes them on Master Data -> Users. The passwords are not
// touched. The customer portal and the driver app keep signing in by email.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'login_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('login_id', 60)->nullable()->unique()->after('email'));
        }

        $taken = DB::table('users')->whereNotNull('login_id')->pluck('login_id')->map(fn ($id) => strtolower((string) $id))->flip()->all();

        // the first super admin (HQ) is "admin"
        $superAdminRoleId = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'super_admin')->value('id') : null;
        $adminId = DB::table('users')->whereNull('login_id')
            ->where(function ($query) use ($superAdminRoleId) {
                $query->where('is_hq', true);
                if ($superAdminRoleId) {
                    $query->orWhereIn('id', DB::table('model_has_roles')->where('role_id', $superAdminRoleId)->select('model_id'));
                }
            })
            ->orderBy('id')->value('id');

        if ($adminId && ! isset($taken['admin'])) {
            DB::table('users')->where('id', $adminId)->update(['login_id' => 'admin']);
            $taken['admin'] = true;
        }

        foreach (DB::table('users')->whereNull('login_id')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '', explode('@', (string) $user->email)[0]));
            $base = substr($base !== '' ? $base : 'user'.$user->id, 0, 50);
            $candidate = $base;
            for ($n = 2; isset($taken[$candidate]); $n++) {
                $candidate = $base.$n;
            }

            DB::table('users')->where('id', $user->id)->update(['login_id' => $candidate]);
            $taken[$candidate] = true;
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'login_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['login_id']);
                $table->dropColumn('login_id');
            });
        }
    }
};
