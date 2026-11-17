<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The company picked under "Company access" is saved with the user, and can be changed later (client, 17 Nov 2026). */
class UserCompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_access_is_saved_on_a_new_user_and_changed_on_edit(): void
    {
        $this->seed();
        Permissions::flush();

        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $kl = Branch::query()->where('code', 'KL')->firstOrFail();
        $jb = Branch::query()->where('code', 'JB')->firstOrFail();
        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($kl->defaultCompany());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Staff', 'login_id' => 'newstaff', 'password' => '1234',
                'branch_ids' => [$kl->id],
                'role_id' => Role::query()->where('name', 'salesperson')->value('id'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('login_id', 'newstaff')->firstOrFail();
        $this->assertSame(['KL'], $user->branches()->pluck('code')->all());
        $this->assertSame([$kl->defaultCompany()->id], $user->companies()->pluck('companies.id')->all());

        // a user saved without a company gets one on the edit page; KL is swapped for JB
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormSet(['branch_ids' => [$kl->id]])
            ->fillForm(['branch_ids' => [$jb->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['JB'], $user->fresh()->branches()->pluck('code')->all());
        $this->assertSame([$jb->defaultCompany()->id], $user->fresh()->companies()->pluck('companies.id')->all());
    }
}
