<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commission by returned CSN (user, 21 Oct 2026): a batch takes the CSNs returned on or before its cutoff date; each
 * CSN is in one batch only (commission_batch_csns), and the batch page lists them. Batches and slips made without a
 * company (so hidden from the lists) get their branch's company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_batch_csns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_note_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        foreach (['commission_batches', 'commission_slips'] as $table) {
            foreach (DB::table($table)->whereNull('company_id')->get(['id', 'source_branch_id']) as $row) {
                $companyId = DB::table('companies')->where('branch_id', $row->source_branch_id)->orderByDesc('is_active')->orderBy('id')->value('id');

                if ($companyId) {
                    DB::table($table)->where('id', $row->id)->update(['company_id' => $companyId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_batch_csns');
    }
};
