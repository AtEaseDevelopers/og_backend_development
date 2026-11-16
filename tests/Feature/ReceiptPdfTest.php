<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Receipt;
use App\Domains\MasterData\Models\Customer;
use App\Support\DocumentPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    /** The portal's receipt download failed: the PDF loads the receipt's customer and the relation was missing. */
    public function test_the_official_receipt_pdf_is_made(): void
    {
        $this->seed();
        $customer = Customer::query()->whereNotNull('company_id')->firstOrFail();
        $payment = Payment::query()->create(['company_id' => $customer->company_id, 'source_branch_id' => $customer->branch_id, 'customer_id' => $customer->id, 'method' => 'bank_transfer', 'amount' => 695.36, 'status' => 'approved']);
        $receipt = Receipt::query()->create(['number' => 'OR261100001', 'source_branch_id' => $customer->branch_id, 'payment_id' => $payment->id, 'customer_id' => $customer->id, 'amount' => 695.36, 'type' => 'official']);

        $this->assertTrue($receipt->customer->is($customer));
        $this->assertStringStartsWith('%PDF', DocumentPdf::receipt($receipt)->output());
    }
}
