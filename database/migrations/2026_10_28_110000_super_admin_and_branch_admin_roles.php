<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles (user, 28 Oct 2026): hq_admin is renamed super_admin (every company, every master data page), and a new
 * branch_admin sees the Master Data "Company" pages (Company, Branches, Staff accounts, System Settings) for the
 * companies given to them only. The users keep their role rows (the role is renamed in place).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'hq_admin')->update(['name' => 'super_admin', 'updated_at' => now()]);

        foreach (DB::table('roles')->select('guard_name')->distinct()->pluck('guard_name')->whenEmpty(fn ($c) => collect(['web'])) as $guard) {
            if (! DB::table('roles')->where('name', 'branch_admin')->where('guard_name', $guard)->exists()) {
                DB::table('roles')->insert(['name' => 'branch_admin', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'super_admin')->update(['name' => 'hq_admin', 'updated_at' => now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
