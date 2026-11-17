<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Domains\Quotation\Actions\ExpressCounterOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Create order -> "Confirm, pay & print" for Term and COD, and a Customer Drop Off without the goods step (client, 17 Nov 2026). */
class ExpressCounterOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_term_and_cod_records_get_their_csn_at_once_also_on_a_customer_drop_off(): void
    {
        $this->seed();

        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $company = Company::query()->where('branch_id', $kl->id)->firstOrFail();
        $at = SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => $company->id, 'branch_id' => $kl->id, 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true]);
        $staff = User::query()->where('is_hq', true)->firstOrFail();
        $sales = User::factory()->create(['sa_location_id' => $at->id]);
        $sales->assignRole('salesperson');
        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();
        $customer->update(['is_credit' => true, 'credit_limit' => 1000000]);

        $pair = ['consignor_name' => 'A', 'consignee_name' => 'B', 'consignee_address' => 'Somewhere', 'service_type' => 'pickup', 'items' => [['item_name' => 'Box', 'uom' => 'Carton', 'quantity' => 1, 'unit_price' => 10]]];
        $order = fn (array $pair) => app(CreateAdminOrder::class)->execute(['customer_id' => $customer->id, 'salesperson_id' => $sales->id, 'pairs' => [$pair]], $staff, $kl, $company)['orders'];

        // COD, pickup: confirmed, Proforma issued, CSN created, nothing paid
        $cod = $order(['order_type' => 'cod'] + $pair);
        $result = app(ExpressCounterOrder::class)->execute($cod, $staff);
        $this->assertTrue($result[0]['ok'], $result[0]['message']);
        $record = $cod->first()->fresh();
        $this->assertNotNull($record->proformaInvoice);
        $this->assertSame(1, $record->consignmentNotes()->count());
        $this->assertSame(0, $record->payments()->count());

        // COD, Customer Drop Off: the goods are at the counter, so the CSN does not wait for "Ready for CSN"
        $drop = $order(['order_type' => 'cod', 'service_type' => 'store', 'store_id' => $at->store->id, 'store_branch_id' => $kl->id] + $pair);
        $result = app(ExpressCounterOrder::class)->execute($drop, $staff);
        $this->assertTrue($result[0]['ok'], $result[0]['message']);
        $this->assertNotNull($drop->first()->fresh()->item_received_at);
        $this->assertSame(1, $drop->first()->fresh()->consignmentNotes()->count());

        // Credit / Term: CSN at once
        $term = $order(['order_type' => 'term'] + $pair);
        $result = app(ExpressCounterOrder::class)->execute($term, $staff);
        $this->assertTrue($result[0]['ok'], $result[0]['message']);
        $this->assertSame(1, $term->first()->fresh()->consignmentNotes()->count());

        // a Cash record is left to its payment
        $cash = $order(['order_type' => 'cash'] + $pair);
        $this->assertSame([], app(ExpressCounterOrder::class)->execute($cash, $staff));
        $this->assertSame(0, $cash->first()->fresh()->consignmentNotes()->count());
    }
}
