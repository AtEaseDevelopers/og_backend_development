<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice for an order with several consignment notes (client, 11 Nov 2026: 1 Order → 2 CSNs → 1 Invoice).
 *
 * An order's consignor–consignee pairs are separate order records, each with its own CSN. Their billing document is
 * now shared: an invoice belongs to every record it bills (invoice_quotation), and each of its lines knows its record
 * (invoice_lines.quotation_id) so that the line gets that record's CSN. Existing invoices keep their one record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_quotation')) {
            Schema::create('invoice_quotation', function (Blueprint $table) {
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->primary(['invoice_id', 'quotation_id']);
            });
        }

        // every invoice issued so far keeps its one record
        DB::table('invoices')
            ->join('quotations', 'quotations.id', '=', 'invoices.quotation_id')
            ->whereNotNull('invoices.quotation_id')
            ->orderBy('invoices.id')
            ->select('invoices.id as invoice_id', 'invoices.quotation_id')
            ->chunk(500, function ($rows): void {
                DB::table('invoice_quotation')->insertOrIgnore(
                    $rows->map(fn ($row) => ['invoice_id' => $row->invoice_id, 'quotation_id' => $row->quotation_id])->all()
                );
            });

        if (! Schema::hasColumn('invoice_lines', 'quotation_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->foreignId('quotation_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_lines', 'quotation_id')) {
            Schema::table('invoice_lines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('quotation_id');
            });
        }

        Schema::dropIfExists('invoice_quotation');
    }
};
