<?php

namespace Tests\Feature;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Domains\Quotation\Actions\ExpressCounterOrder;
use App\Filament\Pages\OrderDetail;
use App\Filament\Resources\ConsignmentNoteResource\Pages\ListConsignmentNotes;
use App\Models\User;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The pickup date of an order and its CSN (client, 18 Nov 2026): CSN management lists a CSN on the day it is picked up. */
class PickupDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_csn_follows_the_orders_pickup_date(): void
    {
        $this->seed();
        Permissions::flush();

        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $company = Company::query()->where('branch_id', $kl->id)->firstOrFail();
        $at = SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => $company->id, 'branch_id' => $kl->id, 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true]);
        $staff = User::query()->where('is_hq', true)->firstOrFail();
        $sales = User::factory()->create(['sa_location_id' => $at->id]);
        $sales->assignRole('salesperson');
        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();
        $customer->update(['is_credit' => true, 'credit_limit' => 1000000]);

        $pair = ['order_type' => 'term', 'consignor_name' => 'A', 'consignee_name' => 'B', 'consignee_address' => 'Somewhere', 'service_type' => 'pickup', 'items' => [['item_name' => 'Box', 'uom' => 'Carton', 'quantity' => 1, 'unit_price' => 10]]];
        $order = fn (array $pair) => app(CreateAdminOrder::class)->execute(['customer_id' => $customer->id, 'salesperson_id' => $sales->id, 'pairs' => [$pair]], $staff, $kl, $company)['orders'];

        // no pickup date keyed in: the day after the order date
        $plain = $order($pair)->first()->fresh();
        $this->assertSame(today()->addDay()->toDateString(), $plain->pickup_date->toDateString());

        // a pickup date keyed in on Create order, and its CSN carries it
        $in5 = today()->addDays(5)->toDateString();
        $records = $order(['pickup_date' => $in5] + $pair);
        app(ExpressCounterOrder::class)->execute($records, $staff);
        $record = $records->first()->fresh();
        $csn = ConsignmentNote::query()->withoutGlobalScopes()->where('quotation_id', $record->id)->firstOrFail();
        $this->assertSame($in5, $record->pickup_date->toDateString());
        $this->assertSame($in5, $csn->pickup_date->toDateString());
        $this->assertSame(today()->toDateString(), $csn->issued_at->toDateString());

        $this->actingAs($staff, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company);

        // CSN management opens on today's pickups: this CSN is listed on its pickup day, not today
        Livewire::test(ListConsignmentNotes::class)
            ->assertCanNotSeeTableRecords([$csn])
            ->call('selectStripDay', $in5)
            ->assertCanSeeTableRecords([$csn]);

        // staff move it earlier on the order page (not before today); the CSN follows
        $page = Livewire::test(OrderDetail::class, ['type' => 'order', 'id' => $record->id])
            ->call('editDetails')
            ->set('details.pickup_date', today()->subDay()->toDateString())
            ->call('saveDetails')
            ->assertHasErrors('details.pickup_date');
        $this->assertSame($in5, $record->fresh()->pickup_date->toDateString());

        $page->set('details.pickup_date', today()->toDateString())->call('saveDetails')->assertHasNoErrors();
        $this->assertSame(today()->toDateString(), $record->fresh()->pickup_date->toDateString());
        $this->assertSame(today()->toDateString(), $csn->fresh()->pickup_date->toDateString());
    }
}
