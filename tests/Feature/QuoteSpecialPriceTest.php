<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Actions\AcceptQuote;
use App\Domains\Quotation\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accept by customer saves a row's special price block as the customer's special price, and adds the new product
 * the row describes ("+ New item…" on Create quotation).
 */
class QuoteSpecialPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_a_quotation_with_a_new_item_adds_the_product_and_its_special_price(): void
    {
        $this->seed();

        $branch = Branch::query()->where('code', 'KL')->firstOrFail();
        $customer = Customer::query()->whereNotNull('code')->where('code', '!=', '')->firstOrFail();
        $location = Location::query()->firstOrCreate(['name' => 'Johor'], ['type' => 'delivery', 'is_active' => true]);

        $quote = Quote::query()->create([
            'company_id' => $customer->company_id,
            'branch_id' => $branch->id,
            'number' => 'Q-TEST-1',
            'customer_id' => $customer->id,
            'quote_date' => '2026-10-21',
            'status' => Quote::PENDING,
            'form' => [],
            'total_amount' => 90,
            'pairs' => [[
                'from_location_id' => $location->id,
                'to_location_id' => $location->id,
                'drop_off_type' => 'default',
                'items' => [[
                    'kind' => Uom::ITEM,
                    'catalog_key' => 'new',
                    'item_name' => 'Steel Drum · up to 200 kg',
                    'quantity' => 2,
                    'manual_price' => '45.00',
                    'product' => ['sub_category' => 'Drum', 'base_item' => 'Steel Drum', 'max_weight' => '200'],
                    'special' => [
                        'pickup_min_charge' => '250', 'construction_min_charge' => null, 'drop_off_min_charge' => '30',
                        'range_a_from' => '1', 'range_a_to' => '9', 'range_a_price' => '45',
                        'range_b_from' => '10', 'range_b_to' => null, 'range_b_price' => '40',
                    ],
                ]],
            ]],
        ]);

        app(AcceptQuote::class)->execute($quote, 'Ms Tan');

        $uom = Uom::query()->where('name', 'Steel Drum · up to 200 kg')->firstOrFail();
        $this->assertSame(Uom::ITEM, $uom->category);
        $this->assertSame('Drum', $uom->sub_category);
        $this->assertEquals(200, (float) $uom->max_weight);

        $tiers = UomRateTier::query()->where('uom_id', $uom->id)->where('customer_code', $customer->code)->orderBy('min_qty')->get();
        $this->assertCount(2, $tiers);
        $this->assertEquals([45.0, 40.0], $tiers->map(fn ($t) => (float) $t->price)->all());
        $this->assertEquals(9, (float) $tiers[0]->max_qty);
        $this->assertNull($tiers[1]->max_qty);
        $this->assertEquals(250, (float) $tiers[0]->pickup_min_charge);
        $this->assertSame('Johor', $tiers[0]->pickup_location);
        $this->assertSame('Quotation '.$quote->number, $tiers[0]->source);
        // effective from the day the customer accepted, no expiry
        $this->assertSame(today()->toDateString(), $tiers[0]->effective_from?->toDateString());
        $this->assertNull($tiers[0]->expires_on);
    }
}
