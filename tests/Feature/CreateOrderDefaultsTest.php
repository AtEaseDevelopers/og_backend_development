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
}
