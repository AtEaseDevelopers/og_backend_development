<?php

use App\Domains\MasterData\Models\Branch;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One order number (ORD series) per enquiry / admin entry. Every consignor–consignee record
 * created from it is a separate quotation with its own quotation, proforma, invoice and CSN
 * numbers, but all of them share this order number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table): void {
            $table->string('order_number', 40)->nullable()->unique()->after('reference_no');
            $table->string('received_through', 30)->nullable()->after('source');
        });

        // Backfill existing enquiries in creation order so numbering stays monotonic per branch
        $numbering = app(DocumentNumberingService::class);
        $branches = Branch::query()->get()->keyBy('id');

        DB::table('portal_enquiries')
            ->whereNull('order_number')
            ->orderBy('id')
            ->get(['id', 'branch_id'])
            ->each(function ($row) use ($numbering, $branches): void {
                $branch = $branches->get($row->branch_id);

                if (! $branch) {
                    return;
                }

                DB::table('portal_enquiries')
                    ->where('id', $row->id)
                    ->update(['order_number' => $numbering->next($branch, DocumentType::Order)]);
            });
    }

    public function down(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table): void {
            $table->dropUnique(['order_number']);
            $table->dropColumn(['order_number', 'received_through']);
        });
    }
};
