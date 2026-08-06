<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('brn');
            $table->string('tin')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'brn']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        $tablesWithBranchId = [
            'customers',
            'drivers',
            'lorries',
            'quotations',
            'credit_approval_requests',
            'ocr_uploads',
            'portal_enquiries',
            'incomplete_delivery_alerts',
        ];

        foreach ($tablesWithBranchId as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->foreignId('company_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
                });
            }
        }

        $tablesWithSourceBranchId = [
            'consignment_notes',
            'delivery_orders',
            'job_sheets',
            'missing_csn_logs',
            'invoices',
            'payments',
            'statements',
            'proforma_invoices',
            'payment_vouchers',
            'commission_batches',
            'commission_slips',
            'commission_purchase_orders',
            'commission_rules',
            'profit_sharing_transactions',
            'sync_logs',
        ];

        foreach ($tablesWithSourceBranchId as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        // Seed one company per existing branch and backfill company_id.
        $branches = DB::table('branches')->orderBy('id')->get();

        foreach ($branches as $branch) {
            $companyId = DB::table('companies')->insertGetId([
                'branch_id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->company_name ?: $branch->name,
                'brn' => $branch->company_no ?: ($branch->code.'-PENDING-BRN'),
                'address' => $branch->address,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'letterhead_path' => $branch->letterhead_path,
                'is_active' => $branch->is_active,
                'registered_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userIds = DB::table('branch_user')->where('branch_id', $branch->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'is_default' => (bool) DB::table('branch_user')
                        ->where('branch_id', $branch->id)
                        ->where('user_id', $userId)
                        ->value('is_default'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($tablesWithBranchId as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                    DB::table($table)->where('branch_id', $branch->id)->update(['company_id' => $companyId]);
                }
            }

            foreach ($tablesWithSourceBranchId as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                    continue;
                }

                if (Schema::hasColumn($table, 'source_branch_id')) {
                    DB::table($table)->where('source_branch_id', $branch->id)->update(['company_id' => $companyId]);
                }

                if ($table === 'job_sheets' && Schema::hasColumn($table, 'operating_branch_id')) {
                    DB::table($table)
                        ->whereNull('company_id')
                        ->where('operating_branch_id', $branch->id)
                        ->update(['company_id' => $companyId]);
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'customers', 'drivers', 'lorries', 'quotations', 'credit_approval_requests',
            'ocr_uploads', 'portal_enquiries', 'incomplete_delivery_alerts',
            'consignment_notes', 'delivery_orders', 'job_sheets', 'missing_csn_logs',
            'invoices', 'payments', 'statements', 'proforma_invoices', 'payment_vouchers',
            'commission_batches', 'commission_slips', 'commission_purchase_orders',
            'commission_rules', 'profit_sharing_transactions', 'sync_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('company_id');
                });
            }
        }

        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
