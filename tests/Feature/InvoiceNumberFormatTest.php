<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invoice numbers without hyphens (user, 28 Oct 2026): IVKL261000001 for an invoice, CBKL261000001 for a cash
 * bill; the migration renumbers the existing ones and their copies.
 */
class InvoiceNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_invoice_and_cash_bill_numbers_have_no_hyphens(): void
    {
        $this->seed();

        $branch = Branch::query()->where('code', 'KL')->firstOrFail();
        $numbering = app(DocumentNumberingService::class);
        $period = now()->format('ym');

        $this->assertMatchesRegularExpression('/^IVKL'.$period.'\d{5}$/', $numbering->next($branch, DocumentType::Invoice));
        $this->assertMatchesRegularExpression('/^CBKL'.$period.'\d{5}$/', $numbering->next($branch, DocumentType::CashBill));
        // the other documents keep their layout
        $this->assertMatchesRegularExpression('/^OR-KL-'.$period.'\d{5}$/', $numbering->next($branch, DocumentType::Receipt));
    }

    public function test_the_migration_renumbers_existing_invoices_and_their_copies(): void
    {
        $this->seed();

        $branch = Branch::query()->where('code', 'KL')->firstOrFail();
        $customer = Customer::query()->firstOrFail();
        $base = ['source_branch_id' => $branch->id, 'customer_id' => $customer->id, 'status' => 'outstanding', 'subtotal' => 100, 'tax_amount' => 0, 'rounding_amount' => 0, 'total_amount' => 100, 'invoice_date' => '2026-10-21', 'due_date' => '2026-10-21', 'created_at' => now(), 'updated_at' => now()];
        $term = DB::table('invoices')->insertGetId($base + ['number' => 'INV-KL-261000009', 'type' => 'term']);
        $cash = DB::table('invoices')->insertGetId($base + ['number' => 'INV-KL-261000010', 'type' => 'cash_bill']);
        $csnId = DB::table('consignment_notes')->orderBy('id')->value('id');
        DB::table('consignment_notes')->where('id', $csnId)->update(['invoice_number' => 'INV-KL-261000010']);
        $refund = DB::table('refund_notes')->insertGetId(['number' => 'RN-KL-261000001', 'source_branch_id' => $branch->id, 'amount' => 10, 'knock_off_invoice_number' => 'INV-KL-261000009', 'created_at' => now(), 'updated_at' => now()]);

        $migration = require database_path('migrations/2026_10_28_200000_invoice_numbers_compact.php');
        $migration->up();

        $this->assertSame('IVKL261000009', DB::table('invoices')->where('id', $term)->value('number'));
        $this->assertSame('CBKL261000010', DB::table('invoices')->where('id', $cash)->value('number'));
        $this->assertSame('CBKL261000010', DB::table('consignment_notes')->where('id', $csnId)->value('invoice_number'));
        $this->assertSame('IVKL261000009', DB::table('refund_notes')->where('id', $refund)->value('knock_off_invoice_number'));
        // the counters continue after the renumbered invoices
        $this->assertSame(9, (int) DB::table('document_number_sequences')->where('branch_id', $branch->id)->where('document_type', 'INV')->where('period', '2610')->value('last_number'));
        $this->assertSame(10, (int) DB::table('document_number_sequences')->where('branch_id', $branch->id)->where('document_type', 'CBL')->where('period', '2610')->value('last_number'));
    }
}
