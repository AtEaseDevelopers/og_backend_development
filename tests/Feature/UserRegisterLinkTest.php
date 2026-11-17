<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Master Data -> Users (user, 17 Nov 2026): the Register Link is allowed or disabled user by user; a salesperson's link is copied from the list. */
class UserRegisterLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_disables_the_register_link_of_a_user(): void
    {
        $this->seed();
        Permissions::flush();

        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $sales = User::factory()->create(['name' => 'Sales One', 'login_id' => 'sales.one']);
        $sales->assignRole('salesperson');
        $sales->assignToBranch($kl, true);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($kl->defaultCompany());

        $this->assertTrue($sales->fresh()->registerLinkAllowed());

        $list = Livewire::test(ListUsers::class)
            ->set('tableSearch', 'sales.one')
            ->assertCanSeeTableRecords([$sales])
            ->assertTableColumnDoesNotExist('email')
            ->assertSee('Copy link')
            ->assertSee($sales->fresh()->orderingLink());

        $list->call('updateTableColumnState', 'register_link_enabled', (string) $sales->getKey(), false);

        $this->assertFalse($sales->fresh()->registerLinkAllowed());
        Livewire::test(ListUsers::class)->set('tableSearch', 'sales.one')->assertDontSee('Copy link');

        // the disabled user has no Register Link button at the top of the page
        $this->actingAs($sales->fresh(), 'web');
        $this->assertStringNotContainsString('Register Link', view('filament.hooks.register-link')->render());

        $sales->update(['register_link_enabled' => true]);
        $this->actingAs($sales->fresh(), 'web');
        $this->assertStringContainsString('Register Link', view('filament.hooks.register-link')->render());
    }
}
