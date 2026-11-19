<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Filament\Pages\CreateOrder;
use App\Models\User;
use App\Support\OrderFormOptions;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Create order (client, 17 Nov 2026): the consignor address starts as the billing address; a Customer Drop Off starts on the admin's own store with its area as the From. */
class CreateOrderDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_consignor_card_fills_itself(): void
    {
        $this->seed();
        Permissions::flush();

        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $company = Company::query()->where('branch_id', $kl->id)->firstOrFail();
        $at = SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => $company->id, 'branch_id' => $kl->id, 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true]);
        $store = $at->store;
        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $admin->forceFill(['sa_location_id' => $at->id])->saveQuietly();

        // no saved address: the billing address
        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();
        $customer->addresses()->delete();
        $customer->update(['address' => "LOT 1, JALAN DEMO\n42100 KLANG"]);
        $state = OrderFormOptions::consignorStateForCustomer((string) $customer->id);
        $this->assertSame('LOT 1, JALAN DEMO 42100 KLANG', $state['pickup_location']);
        $this->assertSame(OrderFormOptions::NEW_ADDRESS, $state['pickup_location_preset']);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company);

        // Customer Drop Off: the admin's own store, and the store's area as the From
        $expected = OrderFormOptions::storeDefaults($store->id);
        $page = Livewire::test(CreateOrder::class)
            ->set('orders.0.pairs.0.service_type', 'store')
            ->assertSet('orders.0.pairs.0.store_id', (string) $store->id);

        if ($expected['from_location_id'] !== '') {
            $page->assertSet('orders.0.pairs.0.from_location_id', $expected['from_location_id']);
        }

        $page->assertDontSee('Rated:')->assertSee('Confirm, pay');
    }

    /** The From follows the Consignor Address; each extra pick-up address has its own PICs (client, 18 Nov 2026). */
    public function test_the_from_follows_the_address_and_a_pickup_address_has_its_pics(): void
    {
        $this->seed();
        Permissions::flush();

        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $company = Company::query()->where('branch_id', $kl->id)->firstOrFail();
        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $area = fn (string $name) => (string) \App\Domains\MasterData\Models\Location::query()->firstOrCreate(['name' => $name], ['code' => strtoupper(str_replace(' ', '_', $name)), 'is_active' => true])->id;
        [$klang, $port, $ipoh, $kualaLumpur, $perak] = [$area('Klang'), $area('Port Klang'), $area('Ipoh'), $area('Kuala Lumpur'), $area('Perak')];

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company);
        $code = \App\Support\CurrentCompany::branch()?->code;

        // the town after the postcode, the longer name, not the street, not the state
        $this->assertSame($port, OrderFormOptions::areaIdFromAddress('LOT 5, JALAN DEMO, 42000 PORT KLANG, SELANGOR', $code));
        $this->assertSame($kualaLumpur, OrderFormOptions::areaIdFromAddress("12 Jalan Ipoh,
51200 Kuala Lumpur", $code));
        $this->assertSame($ipoh, OrderFormOptions::areaIdFromAddress('8 Jalan Demo, 30000 Ipoh, Perak', $code));
        $this->assertNull(OrderFormOptions::areaIdFromAddress('NO 1, JALAN DEMO', $code));

        $page = Livewire::test(CreateOrder::class)
            ->set('orders.0.pairs.0.pickup_location', 'LOT 1, JALAN DEMO, 41000 KLANG, SELANGOR')
            ->assertSet('orders.0.pairs.0.from_location_id', $klang)
            // an address that names no area leaves the From as it is
            ->set('orders.0.pairs.0.pickup_location', 'NO 1, JALAN DEMO')
            ->assertSet('orders.0.pairs.0.from_location_id', $klang)
            ->set('orders.0.pairs.0.multi_pickup', true);

        if (($page->get('orders.0.pairs.0.pickup_stops') ?? []) === []) {
            $page->call('inOrder', 0, 'addStop', 0, 'pickup');
        }

        $page->call('inOrder', 0, 'addStopPic', 0, 0)
            ->assertSee('Pick-up address 2')
            ->assertSet('orders.0.pairs.0.pickup_stops.0.pics_extra', [['name' => '', 'phone' => '']]);

        // saved joined, like the consignor's own PICs
        $stops = \App\Support\OrderStops::merge(['multi_pickup' => true, 'pickup_stops' => [['location' => 'Warehouse B', 'pic_name' => 'ALI', 'pic_phone' => '0100000001', 'pics_extra' => [['name' => 'MEI', 'phone' => '0100000002']]]]]);
        $this->assertSame('ALI / MEI', $stops[0]['pic_name']);
        $this->assertSame('0100000001 / 0100000002', $stops[0]['pic_phone']);
    }
}
