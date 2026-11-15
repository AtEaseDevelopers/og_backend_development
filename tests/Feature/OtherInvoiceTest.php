<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\OtherInvoice;
use App\Domains\Billing\Models\OtherItem;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\OtherInvoiceResource;
use App\Filament\Resources\OtherInvoiceResource\Pages\CreateOtherInvoice;
use App\Filament\Resources\OtherInvoiceResource\Pages\ListOtherInvoices;
use App\Filament\Resources\OtherItemResource\Pages\CreateOtherItem;
use App\Filament\Resources\OtherItemResource\Pages\ListOtherItems;
use App\Http\Controllers\Admin\OtherInvoicePdfController;
use App\Models\User;
use App\Support\Permissions;
use App\Support\RecordUsage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Other Invoice (client + user, 16 Nov 2026): items from Master Data → Other Items, a description and an amount per
 * line, numbered OIYYMM#####, printed in the invoice's format.
 */
class OtherInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_other_invoice_is_made_from_other_items_and_numbered_oi(): void
    {
        $this->seed();
        Permissions::flush();
        RecordUsage::flush();

        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $customer = Customer::query()->whereNotNull('company_id')->firstOrFail();

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($customer->company);

        // Master Data → Other Items: a description only
        Livewire::test(CreateOtherItem::class)->fillForm(['name' => 'Payment on Behalf'])->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateOtherItem::class)->fillForm(['name' => 'Rental Income'])->call('create')->assertHasNoFormErrors();
        $behalf = OtherItem::query()->where('name', 'Payment on Behalf')->firstOrFail();
        $rental = OtherItem::query()->where('name', 'Rental Income')->firstOrFail();
        $this->assertTrue($behalf->is_active);

        // the invoice: the customer, the date, two lines
        Livewire::test(CreateOtherInvoice::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'invoice_date' => '2026-12-05',
                'charges_sst' => true,
                'lines' => [
                    ['other_item_id' => $behalf->id, 'description' => 'DIESEL LORRY NO. ABC1234 OCT 26', 'amount' => 350.50],
                    ['other_item_id' => $rental->id, 'description' => 'PARKING RENTAL CHARGES XYZ3000 DEC 26', 'amount' => 200],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = OtherInvoice::query()->firstOrFail();
        $this->assertSame('OI261200001', $invoice->number);
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('Payment on Behalf', $invoice->lines[0]->item_name);
        $this->assertSame('550.50', (string) $invoice->subtotal);
        $this->assertSame('33.03', (string) $invoice->tax_amount);
        $this->assertSame('583.53', (string) $invoice->total_amount);

        // the next one of the month follows; another month starts again
        $this->assertSame('OI261200002', OtherInvoice::nextNumber(new \DateTime('2026-12-20')));
        $this->assertSame('OI270100001', OtherInvoice::nextNumber(new \DateTime('2027-01-02')));

        // the list shows it; the document prints the item, the description and the totals
        Livewire::test(ListOtherInvoices::class)->assertCanSeeTableRecords([$invoice])->assertSee('OI261200001');
        $document = OtherInvoicePdfController::document($invoice->fresh());
        $html = view('pdf.invoice', ['document' => $document, 'meta' => $document['meta']])->render();
        foreach (['OTHER INVOICE', 'OI261200001', 'Payment on Behalf', 'DIESEL LORRY NO. ABC1234 OCT 26', '350.50', 'Sub Total (Excluding Tax)', 'Service Tax 6%', '583.53'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Terms &amp; Conditions', $html);
        $this->assertStringContainsString('other-invoices/'.$invoice->id.'/pdf', OtherInvoiceResource::pdfUrl($invoice));

        // an item used on an invoice cannot be deleted; an unused one can
        $unused = OtherItem::query()->create(['company_id' => $customer->company_id, 'name' => 'Land Rental Income', 'is_active' => true]);
        Livewire::test(ListOtherItems::class)->assertTableActionHidden('delete', $behalf)->assertTableActionVisible('delete', $unused);
    }
}
