<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order's branch = its documents' prefix (client, 17 Nov 2026): the staff who keys the order in, not the
 * salesperson; a Customer Drop Off takes the branch of the store that receives the goods.
 */
class OrderBranchPrefixTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_order_follows_the_staff_and_a_drop_off_follows_the_store(): void
    {
        $this->seed();

        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $jb = Branch::query()->where('code', 'JB')->firstOrFail();
        $company = Company::query()->where('branch_id', $kl->id)->firstOrFail();
        $at = fn (Branch $b, string $code) => SaLocation::query()->firstOrCreate(['csn_prefix' => $code], ['company_id' => Company::query()->where('branch_id', $b->id)->value('id'), 'branch_id' => $b->id, 'code' => $code, 'name' => $code.' Branch', 'is_active' => true]);
        $klAt = $at($kl, 'KL');
        $jbAt = $at($jb, 'JB');

        $staff = User::factory()->create(['sa_location_id' => $klAt->id]);
        $sales = User::factory()->create(['sa_location_id' => $jbAt->id]);
        $sales->assignRole('salesperson');
        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();

        $pair = ['consignor_name' => 'A', 'consignee_name' => 'B', 'consignee_address' => 'Somewhere', 'order_type' => 'cash', 'service_type' => 'pickup', 'items' => [['item_name' => 'Box', 'uom' => 'Carton', 'quantity' => 1, 'unit_price' => 10]]];
        $order = fn (array $pair) => app(CreateAdminOrder::class)->execute(['customer_id' => $customer->id, 'salesperson_id' => $sales->id, 'pairs' => [$pair]], $staff, $kl, $company);

        // keyed in by KL staff with a JB salesperson: KL
        $made = $order($pair);
        $this->assertSame($klAt->id, $made['enquiry']->sa_location_id);
        $this->assertStringStartsWith('OFKL', $made['enquiry']->order_number);
        $this->assertSame($klAt->id, $made['orders']->first()->sa_location_id);
        $this->assertSame($sales->id, $made['orders']->first()->salesperson_id);

        // Customer Drop Off at a JB store: JB
        $made = $order(['service_type' => 'store', 'store_id' => $jbAt->store->id, 'store_branch_id' => $jb->id] + $pair);
        $this->assertStringStartsWith('OFJB', $made['enquiry']->order_number);
        $this->assertSame($jbAt->id, $made['orders']->first()->sa_location_id);
        $this->assertStringStartsWith('QJB', $made['orders']->first()->number);
    }
}
