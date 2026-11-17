<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Driver;
use App\Filament\Resources\DriverResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A driver keyed in twice is caught on its name and its IC (client, 17 Nov 2026). */
class DriverDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_ic_or_same_name_is_a_duplicate(): void
    {
        $ali = Driver::query()->create(['name' => 'Ali Bin Abu', 'ic_number' => '900101-01-1234']);
        Driver::query()->create(['name' => 'Muthu']);

        // the same IC, typed without dashes, under another name
        $this->assertSame($ali->id, DriverResource::duplicate('ic', 'Someone Else', '900101011234', null)?->id);
        // the same name, other letter case, no IC given
        $this->assertSame($ali->id, DriverResource::duplicate('name', ' ali bin abu ', null, null)?->id);
        $this->assertNotNull(DriverResource::duplicate('name', 'MUTHU', '880202-02-5678', null));

        // two people with one name: both have an IC and the ICs differ
        $this->assertNull(DriverResource::duplicate('name', 'Ali Bin Abu', '880202-02-5678', null));
        // the driver being edited is not its own duplicate; no IC is not "the same IC"
        $this->assertNull(DriverResource::duplicate('ic', 'Ali Bin Abu', '900101-01-1234', null, $ali->id));
        $this->assertNull(DriverResource::duplicate('name', 'Ali Bin Abu', '900101-01-1234', null, $ali->id));
        $this->assertNull(DriverResource::duplicate('ic', 'New Driver', '', null));
    }

    public function test_the_driver_form_refuses_a_driver_keyed_in_twice(): void
    {
        $this->seed();
        \App\Support\Permissions::flush();

        $admin = \App\Models\User::query()->where('is_hq', true)->firstOrFail();
        $company = \App\Domains\MasterData\Models\Company::query()->firstOrFail();
        $this->actingAs($admin, 'web');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Filament\Facades\Filament::setTenant($company);

        $ali = Driver::query()->create(['company_id' => $company->id, 'branch_id' => $company->branch_id, 'name' => 'Ali Bin Abu', 'ic_number' => '900101-01-1234']);
        $create = DriverResource\Pages\CreateDriver::class;

        \Livewire\Livewire::test($create)->fillForm(['name' => 'ALI BIN ABU'])->call('create')->assertHasFormErrors(['name']);
        \Livewire\Livewire::test($create)->fillForm(['name' => 'Other Name', 'ic_number' => '900101011234'])->call('create')->assertHasFormErrors(['ic_number']);
        $this->assertSame(1, Driver::query()->whereIn('name', ['ALI BIN ABU', 'Other Name'])->orWhere('id', $ali->id)->count());

        // another person of the same name, and saving the driver itself again, go through
        \Livewire\Livewire::test($create)->fillForm(['name' => 'Ali Bin Abu', 'ic_number' => '880202-02-5678'])->call('create')->assertHasNoFormErrors();
        \Livewire\Livewire::test(DriverResource\Pages\EditDriver::class, ['record' => $ali->getRouteKey()])
            ->fillForm(['phone' => '0123456789'])->call('save')->assertHasNoFormErrors();
    }
}