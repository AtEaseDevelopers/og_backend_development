<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lead;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\LorryCompany;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\FleetVehicleResource\Pages\ListFleetVehicles;
use App\Filament\Resources\LorryCompanyResource\Pages\ListLorryCompanies;
use App\Models\User;
use App\Support\Permissions;
use App\Support\RecordUsage;
use App\Support\SpecialPriceExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master Data (client, 16 Nov 2026): Delete only for a record without history; the Fleet Vehicles is the page to
 * search a lorry and see its company, driver and attendant; a lorry company lists its lorries; every customer's
 * special prices export in one workbook.
 */
class MasterDataDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_is_only_for_unused_records_and_the_lorry_database_shows_company_and_driver(): void
    {
        $this->seed();
        Permissions::flush();
        RecordUsage::flush();

        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $used = Customer::query()->whereNotNull('company_id')->whereIn('id', \Illuminate\Support\Facades\DB::table('quotations')->select('customer_id'))->firstOrFail();
        $company = $used->company;

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company);

        $fresh = Customer::query()->create(['company_id' => $company->id, 'branch_id' => $used->branch_id, 'code' => '3000-ZZZ9', 'company_name' => 'Never Used Sdn Bhd', 'status' => 'active']);
        $fresh->pics()->create(['name' => 'MS. TAN', 'phone' => '0123456789', 'is_default' => true]);

        $this->assertTrue(RecordUsage::isUsed($used));
        $this->assertFalse(RecordUsage::isUsed($fresh));

        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('delete', $used)
            ->assertTableActionVisible('delete', $fresh)
            ->callTableAction('delete', $fresh);
        $this->assertNull(Customer::query()->find($fresh->id));
        $this->assertNotNull(Customer::query()->find($used->id));

        // a lorry under a company, with its driver and attendant
        $lorryCompany = LorryCompany::query()->create(['company_id' => $company->id, 'code' => 'G003', 'name' => 'Demo Lorry Co Sdn Bhd', 'pic' => 'MR. TAN', 'contact' => '03-5555 0100', 'is_active' => true]);
        $driver = Driver::query()->create(['company_id' => $company->id, 'branch_id' => $used->branch_id, 'name' => 'Driver One', 'phone' => '011-5550 1001', 'attendant_name' => 'Attendant One', 'attendant_phone' => '013-555 0102', 'is_active' => true]);
        $lorry = Lorry::query()->create(['company_id' => $company->id, 'branch_id' => $used->branch_id, 'registration_no' => 'ABC 1234', 'type' => '24 FT', 'capacity' => 5000, 'lorry_company_id' => $lorryCompany->id, 'default_driver_id' => $driver->id, 'status' => 'available', 'is_active' => true]);
        $other = Lorry::query()->create(['company_id' => $company->id, 'branch_id' => $used->branch_id, 'registration_no' => 'ZZZ 1', 'status' => 'available', 'is_active' => true]);

        // searched by the company, the driver's phone or the lorry no.
        foreach (['Demo Lorry', '011-5550', 'ABC 1234', 'Attendant One'] as $search) {
            Livewire::test(ListFleetVehicles::class)->searchTable($search)->assertCanSeeTableRecords([$lorry])->assertCanNotSeeTableRecords([$other]);
        }
        Livewire::test(ListFleetVehicles::class)->assertSee('011-5550 1001')->assertSee('013-555 0102')->assertSee('Demo Lorry Co Sdn Bhd');

        // the company lists its lorries; one with a lorry is not deletable
        $this->assertTrue(RecordUsage::isUsed($lorryCompany));
        Livewire::test(ListLorryCompanies::class)->assertTableActionHidden('delete', $lorryCompany)->assertSee('MR. TAN');
        // View on the company: its details and its lorries
        Livewire::test(ListLorryCompanies::class)->mountTableAction('view', $lorryCompany)->assertSee('ABC 1234')->assertSee('011-5550 1001')->assertSee('Attendant One');
        // the drivers list is searched by the lorry company and by the attendant too
        $driver->update(['lorry_company' => 'Demo Lorry Co Sdn Bhd', 'lorry_company_code' => 'G003', 'lorry_number' => 'ABC 1234']);
        foreach (['Demo Lorry', 'G003', 'Attendant One', '013-555', 'ABC 1234'] as $search) {
            Livewire::test(\App\Filament\Resources\DriverResource\Pages\ListDrivers::class)->searchTable($search)->assertCanSeeTableRecords([$driver]);
        }
        Livewire::test(\App\Filament\Resources\DriverResource\Pages\ListDrivers::class)->searchTable('no such driver zz')->assertCanNotSeeTableRecords([$driver]);
        $html = view('filament.infolists.lorry-company-lorries', ['getRecord' => fn () => $lorryCompany])->render();
        $this->assertStringContainsString('ABC 1234', $html);
        $this->assertStringContainsString('011-5550 1001', $html);

        // an inactive lead is not offered on Create quotation
        $lead = Lead::query()->create(['company_id' => $company->id, 'name' => 'Sleeping Lead', 'is_active' => false]);
        $this->assertFalse($lead->fresh()->is_active);

        // every customer's special prices in one workbook
        $response = SpecialPriceExport::downloadAll();
        $this->assertFileExists($response->getFile()->getPathname());
        $this->assertStringContainsString('all customers', $response->headers->get('content-disposition'));
    }
}
