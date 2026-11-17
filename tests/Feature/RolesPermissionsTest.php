<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Company;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\RolesPermissionsPage;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // the roles and permissions kept in memory belong to the database of the test before
        Permissions::flush();
        $this->seed();
        Permissions::flush();
    }

    private function admin(): User
    {
        return User::query()->where('is_hq', true)->first() ?? User::query()->role('super_admin')->firstOrFail();
    }

    private function actingOnPanel(User $user): void
    {
        $this->actingAs($user, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant(Company::query()->firstOrFail());
        Permissions::flush();
    }

    public function test_each_role_starts_with_what_it_could_do_before(): void
    {
        $finance = Role::findByName('finance', 'web');
        $clerk = Role::findByName('salesperson', 'web');

        $this->assertTrue($finance->hasPermissionTo('reports.view'));
        $this->assertTrue($finance->hasPermissionTo('billing.review_payment'));
        $this->assertFalse($finance->hasPermissionTo('orders.override_price'));
        $this->assertFalse($clerk->hasPermissionTo('reports.view'));
        $this->assertTrue($clerk->hasPermissionTo('orders.create'));
        // nobody but a super admin changes roles and permissions to start with
        $this->assertFalse(Role::findByName('branch_admin', 'web')->hasPermissionTo('settings.roles'));
    }

    public function test_a_box_ticked_and_unticked_on_the_page_changes_what_the_role_may_do(): void
    {
        $this->actingOnPanel($this->admin());
        $key = RolesPermissionsPage::key('reports.view');

        Livewire::test(RolesPermissionsPage::class)
            ->assertSet("matrix.$key.salesperson", false)
            ->assertSet("matrix.$key.finance", true)
            ->set("matrix.$key.salesperson", true)
            ->call('save');
        $this->assertTrue(Role::findByName('salesperson', 'web')->fresh()->hasPermissionTo('reports.view'));
        // the other roles are as they were
        $this->assertTrue(Role::findByName('finance', 'web')->fresh()->hasPermissionTo('reports.view'));

        Livewire::test(RolesPermissionsPage::class)
            ->assertSet("matrix.$key.salesperson", true)
            ->set("matrix.$key.salesperson", false)
            ->set("matrix.$key.finance", false)
            ->call('save');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse(Role::findByName('salesperson', 'web')->fresh()->hasPermissionTo('reports.view'));
        $this->assertFalse(Role::findByName('finance', 'web')->fresh()->hasPermissionTo('reports.view'));
    }

    public function test_the_page_is_for_those_with_the_permission_only(): void
    {
        $finance = User::query()->role('finance')->firstOrFail();
        $this->actingOnPanel($finance);

        $this->assertFalse(RolesPermissionsPage::canAccess());
        $this->assertTrue(ReportsHub::canAccess());
    }

    public function test_a_role_can_be_view_only_on_a_module(): void
    {
        $role = Role::findByName('counter', 'web');
        // to start with a counter clerk does everything on customers
        $this->assertTrue($role->hasPermissionTo('customers.view'));
        $this->assertTrue($role->hasPermissionTo('customers.edit'));

        $role->revokePermissionTo(['customers.create', 'customers.edit', 'customers.delete']);
        $clerk = User::query()->role('counter')->firstOrFail();
        $clerk->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        $this->actingOnPanel($clerk);
        $customer = \App\Domains\MasterData\Models\Customer::query()->firstOrFail();

        $resource = \App\Filament\Resources\CustomerResource::class;
        $this->assertTrue($resource::canViewAny());
        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit($customer));
        $this->assertFalse($resource::canDelete($customer));
        // another module is untouched
        $this->assertTrue(\App\Filament\Resources\LocationResource::canCreate());

        // on the list itself: no New, no Import, no Edit on a row; View stays
        $customer = \App\Domains\MasterData\Models\Customer::query()->whereNotNull('company_id')->firstOrFail();
        Filament::setTenant($customer->company);
        Livewire::test(\App\Filament\Resources\CustomerResource\Pages\ListCustomers::class)
            ->assertActionHidden('create')
            ->assertActionHidden('importSpecialPrice')
            ->assertTableActionHidden('edit', $customer)
            ->assertTableActionVisible('view', $customer);

        // with the Edit and Create ticks back, the buttons are back
        $role->givePermissionTo(['customers.create', 'customers.edit']);
        Permissions::flush();
        $this->actingOnPanel($clerk->fresh());
        Filament::setTenant($customer->company);
        Livewire::test(\App\Filament\Resources\CustomerResource\Pages\ListCustomers::class)
            ->assertActionVisible('create')
            ->assertActionVisible('importSpecialPrice')
            ->assertTableActionVisible('edit', $customer);
        $role->revokePermissionTo(['customers.create', 'customers.edit']);
        Permissions::flush();
        $this->actingOnPanel($clerk->fresh());

        // the custom buttons of the module's screens follow the same ticks; reading ones stay
        $this->assertSame('view', \App\Support\ActionGate::abilityOf('previewPdf'));
        $this->assertSame('view', \App\Support\ActionGate::abilityOf('customerHistory'));
        $this->assertSame('create', \App\Support\ActionGate::abilityOf('importSpecialPrice'));
        $this->assertSame('delete', \App\Support\ActionGate::abilityOf('delete'));
        $this->assertSame('edit', \App\Support\ActionGate::abilityOf('assignLorry'));
        $this->assertFalse(Permissions::can($resource, 'edit'));
        $this->assertTrue(Permissions::can($resource, 'view'));
        // a module without a Delete tick (orders) asks for Edit instead
        $this->assertTrue(Permissions::can(\App\Filament\Pages\Orders::class, 'delete'));
    }

    public function test_staff_sign_in_to_the_admin_panel_with_their_user_id(): void
    {
        $admin = $this->admin();
        // a user saved without a Staff ID gets one from their email
        $this->assertNotEmpty($admin->login_id);
        $admin->forceFill(['login_id' => 'Admin'])->save();
        $this->assertSame('admin', $admin->fresh()->login_id);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // the email no longer signs in; the Staff ID does, in any case
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm(['login_id' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasErrors(['data.login_id']);
        $this->assertGuest('web');

        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm(['login_id' => 'ADMIN', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasErrors(['data.login_id']);

        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm(['login_id' => ' ADMIN ', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($admin->fresh(), 'web');
    }

    public function test_an_admin_sees_and_changes_a_users_password_on_the_users_page(): void
    {
        $admin = $this->admin();
        $clerk = User::query()->role('counter')->firstOrFail();
        $clerk->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        $oldHash = $clerk->password;
        $this->actingOnPanel($admin);

        // a password set before the copy was kept is not shown; saving without the tick leaves it alone
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->assertSet('data.current_password', null)
            ->assertSee('Not recorded')
            ->assertSee('Change password')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($oldHash, $clerk->fresh()->password);

        // ticked: a new password is typed, saved hashed, and shown as text afterwards
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->set('data.change_password', true)
            ->set('data.password', 'Lorry-2026')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('data.change_password', false)
            ->assertSet('data.current_password', 'Lorry-2026');
        $clerk = $clerk->fresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Lorry-2026', $clerk->password));
        $this->assertSame('Lorry-2026', $clerk->password_readable);
        $this->assertStringNotContainsString('Lorry-2026', (string) \Illuminate\Support\Facades\DB::table('users')->where('id', $clerk->id)->value('password_readable'));
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])->assertSet('data.current_password', 'Lorry-2026');

        // ticked and left empty: a new password is asked for
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->set('data.change_password', true)
            ->call('save')
            ->assertHasFormErrors(['password' => 'required']);

        // changed somewhere else (a reset link): the copy would be wrong, so it is dropped
        $clerk->forceFill(['password' => 'Another-1'])->save();
        $this->assertNull($clerk->fresh()->password_readable);
    }

    public function test_a_user_has_one_role_picked_on_the_users_page(): void
    {
        $admin = $this->admin();
        $clerk = User::query()->role('counter')->firstOrFail();
        $clerk->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        $finance = Role::findByName('finance', 'web');
        $this->actingOnPanel($admin);

        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->assertSet('data.role_id', Role::findByName('counter', 'web')->id)
            ->set('data.role_id', $finance->id)
            ->call('save')
            ->assertHasNoFormErrors();

        Permissions::flush();
        $this->assertSame(['finance'], $clerk->fresh()->roles->pluck('name')->all());

        // a driver's hidden role stays when a staff role is picked
        $driver = User::query()->role('driver')->firstOrFail();
        $driver->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        Livewire::test(EditUser::class, ['record' => $driver->getRouteKey()])
            ->assertSet('data.role_id', null)
            ->set('data.role_id', $finance->id)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing(['driver', 'finance'], $driver->fresh()->roles->pluck('name')->all());
    }

    public function test_the_organisation_chart_lists_companies_branches_and_users_with_filters(): void
    {
        $this->actingOnPanel($this->admin());
        $company = Company::query()->where('is_active', true)->orderBy('code')->firstOrFail();
        $clerk = User::query()->role('counter')->whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))->firstOrFail();
        $clerk->forceFill(['phone' => '012-555 0101'])->save();
        $finance = User::query()->role('finance')->firstOrFail();

        Livewire::test(\App\Filament\Pages\OrganisationChartPage::class)
            ->assertSee($company->name)
            ->assertSee('Company staff')
            ->assertSee($clerk->name)
            ->assertSee('012-555 0101')
            ->assertSee($clerk->email)
            ->assertSee('Counter')
            // by role
            ->set('roleFilter', 'finance')
            ->assertSee($finance->name)
            ->assertDontSee($clerk->email)
            // by user
            ->set('roleFilter', '')
            ->set('userFilter', $clerk->login_id)
            ->assertSee($clerk->email)
            ->assertDontSee($finance->email)
            // by company
            ->set('userFilter', '')
            ->set('companyFilter', (string) $company->id)
            ->assertSee($company->name)
            ->assertSee('1 company')
            ->call('clearFilters')
            ->assertSet('companyFilter', '')
            ->set('userFilter', 'nobody-zz')
            ->assertSee('No user matches these filters.');
    }

    public function test_buttons_are_at_the_top_and_hidden_without_the_permission(): void
    {
        $admin = $this->admin();
        $clerk = User::query()->role('counter')->firstOrFail();
        $clerk->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        $this->actingOnPanel($admin);

        // the form's buttons are in the page header (top right), none under the form
        $page = Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->assertActionVisible('save')
            ->assertActionVisible('cancel');
        $headerNames = collect($page->instance()->getCachedHeaderActions())->map(fn ($action) => $action->getName())->all();
        $this->assertSame(['cancel', 'save'], array_values(array_intersect($headerNames, ['cancel', 'save'])));
        $this->assertSame([], $page->instance()->getCachedFormActions());

        // Delete is first and apart, the page's other buttons next, then Cancel, and Save at the far right
        $customer = \App\Domains\MasterData\Models\Customer::query()->whereNotNull('company_id')->firstOrFail();
        Filament::setTenant($customer->company);
        $names = collect(Livewire::test(\App\Filament\Resources\CustomerResource\Pages\EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->instance()->getCachedHeaderActions())->map(fn ($action) => $action->getName())->values()->all();
        $this->assertSame('save', end($names));
        $this->assertSame('cancel', $names[count($names) - 2]);
        if (in_array('delete', $names, true)) {
            $this->assertSame('delete', $names[0]);
        }

        // a salesperson cannot decide a credit approval or review a payment: the buttons are not there at all
        $this->assertFalse(Role::findByName('salesperson', 'web')->hasPermissionTo('orders.credit_approval'));
        $gate = new \ReflectionClass(\App\Support\ActionGate::class);
        $special = $gate->getConstant('SPECIAL');
        $this->assertSame('orders.credit_approval', $special['OrderDetail:creditDecision']);
        $this->assertSame('billing.review_payment', $special['PaymentSubmissionResource:approve']);
        foreach ($special as $permission) {
            $this->assertArrayHasKey($permission, Permissions::catalog());
        }
    }

    public function test_one_users_own_exception_wins_over_the_role(): void
    {
        $admin = $this->admin();
        // a counter clerk: the role cannot open reports and can create orders
        $clerk = User::query()->role('counter')->firstOrFail();
        $clerk->forceFill(['sa_location_id' => \App\Domains\MasterData\Models\SaLocation::query()->firstOrCreate(['csn_prefix' => 'KL'], ['company_id' => \App\Domains\MasterData\Models\Company::query()->value('id'), 'branch_id' => \App\Domains\MasterData\Models\Branch::query()->where('code', 'KL')->value('id'), 'code' => 'KL', 'name' => 'KL Branch', 'is_active' => true])->id])->saveQuietly();
        $this->assertFalse(Permissions::allows('reports.view', $clerk));
        $this->assertTrue(Permissions::allows('orders.create', $clerk));

        // on the user's edit page: tick reports (the role cannot), untick creating orders (the role can)
        $this->actingOnPanel($admin);
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->assertSee('Permissions (this user only)')
            ->assertSet('data.permission_effective.reports__view', false)
            ->assertSet('data.permission_effective.orders__create', true)
            ->set('data.permission_effective.reports__view', true)
            ->set('data.permission_effective.orders__create', false)
            ->call('save')
            ->assertHasNoFormErrors();

        Permissions::flush();
        $clerk = $clerk->fresh();
        $this->assertSame(['orders.create' => 'deny', 'reports.view' => 'allow'], collect(Permissions::overridesFor($clerk))->sortKeys()->all());
        $this->assertTrue(Permissions::allows('reports.view', $clerk));
        $this->assertFalse(Permissions::allows('orders.create', $clerk));
        // another user of the same role still follows the role
        $other = User::query()->role('counter')->whereKeyNot($clerk->id)->first();
        if ($other) {
            $this->assertFalse(Permissions::allows('reports.view', $other));
            $this->assertTrue(Permissions::allows('orders.create', $other));
        }

        // the boxes put back as the role: no exception is left
        $this->actingOnPanel($admin);
        Livewire::test(EditUser::class, ['record' => $clerk->getRouteKey()])
            ->assertSet('data.permission_effective.reports__view', true)
            ->set('data.permission_effective.reports__view', false)
            ->set('data.permission_effective.orders__create', true)
            ->call('save')
            ->assertHasNoFormErrors();

        Permissions::flush();
        $this->assertSame([], Permissions::overridesFor($clerk));
        $this->assertFalse(Permissions::allows('reports.view', $clerk->fresh()));
    }
}
