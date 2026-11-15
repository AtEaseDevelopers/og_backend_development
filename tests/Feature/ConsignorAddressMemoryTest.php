<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\ConsignorConsignee;
use App\Domains\Quotation\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An address keyed for a consignor / consignee on an order is kept in Master Data (user, 16 Nov 2026), so a company
 * with several addresses is offered once per address the next time.
 */
class ConsignorAddressMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_keeps_the_consignee_addresses_it_was_keyed_with(): void
    {
        $this->seed();

        $order = Quotation::query()->whereNotNull('customer_id')->firstOrFail();
        $rows = fn () => ConsignorConsignee::query()->withoutGlobalScopes()->where('company_id', $order->company_id)->where('name', 'ACME MARKETING SDN BHD')->orderBy('id')->get();

        // the first address: a new record
        $order->forceFill(['consignee_store_id' => null, 'consignee_name' => 'ACME MARKETING SDN BHD', 'drop_off_location' => 'NO.11, JALAN DEMO 6/11, TAMAN PERINDUSTRIAN DEMO, 47100 PUCHONG, SELANGOR.', 'consignee_pic_name' => 'MR. LEE', 'consignee_pic_phone' => '03-8061 1234'])->save();
        $this->assertCount(1, $rows());
        $this->assertSame('MR. LEE', $rows()[0]->pic_name);
        $this->assertTrue($rows()[0]->customers()->whereKey($order->customer_id)->exists());

        // the same address written another way: no second record
        $order->forceFill(['drop_off_location' => 'No 11 Jalan Demo 6/11 Taman Perindustrian Demo 47100 Puchong Selangor'])->save();
        $this->assertCount(1, $rows());

        // another address of the same company: a second record under the same name
        $order->forceFill(['drop_off_location' => 'NO.17 JALAN CONTOH 33/3, DEMO INDUSTRIAL PARK, SEKSYEN 33, 40460 SHAH ALAM, SELANGOR.'])->save();
        $this->assertCount(2, $rows());
        $this->assertSame('both', $rows()[1]->type);

        // a saved record without an address takes the address instead of a new record being made
        $blank = ConsignorConsignee::query()->create(['company_id' => $order->company_id, 'name' => 'B & C Furnishing Sdn Bhd', 'type' => 'both', 'is_active' => true]);
        $order->forceFill(['consignee_name' => 'B&C FURNISHING SDN BHD', 'drop_off_location' => 'DEMO MALL LOT 5 JB'])->save();
        $this->assertSame('DEMO MALL LOT 5 JB', $blank->fresh()->address);
        $this->assertSame(1, ConsignorConsignee::query()->withoutGlobalScopes()->where('name', 'like', 'B%C FURNISHING%')->count());

        // a store's address (Self Collection) is not a consignee's
        $order->consignee_store_id = 5;
        $order->consignee_name = 'WALK IN CUSTOMER';
        $order->drop_off_location = 'O&G STORE, JALAN 13/108C';
        ConsignorConsignee::rememberFromOrder($order);
        $this->assertSame(0, ConsignorConsignee::query()->withoutGlobalScopes()->where('name', 'WALK IN CUSTOMER')->count());
    }
}
