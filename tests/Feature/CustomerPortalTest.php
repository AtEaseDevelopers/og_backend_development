<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPortalLink;
use App\Mail\SystemNotificationMail;
use App\Models\User;
use App\Support\PortalSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** First-draft customer portal: register, submit an order, admin sees it. */
class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
    }

    public function test_registration_needs_email_verification_and_admin_approval(): void
    {
        $branch = Branch::query()->where('code', 'KL')->firstOrFail();

        $this->post('/portal/register', [
            'name' => 'Ali PIC', 'email' => 'ali@newco.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'NewCo Sdn Bhd', 'brn' => '202601000001', 'tin' => 'C123', 'phone' => '0123456789',
            'address' => '1 Jalan Test, KL', 'branch_id' => $branch->id,
        ])->assertRedirect('/portal/login');

        $user = User::query()->where('email', 'ali@newco.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $customer = Customer::query()->where('brn', '202601000001')->firstOrFail();
        $this->assertFalse((bool) $customer->portal_approved);
        $this->assertSame('Ali PIC', $customer->pics()->first()?->name);
        Mail::assertSent(SystemNotificationMail::class, fn ($m) => str_contains($m->body, '/portal/verify-email/'));

        $this->post('/portal/login', ['email' => 'ali@newco.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get(URL::temporarySignedRoute('portal.verify-email', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertRedirect('/portal/login');
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->post('/portal/login', ['email' => 'ali@newco.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/portal/register', [
            'name' => 'Siti', 'email' => 'siti@newco.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'NewCo', 'brn' => ' 202601000001 ', 'phone' => '011', 'address' => 'x', 'branch_id' => $branch->id,
        ]);
        $this->assertSame(1, Customer::query()->where('brn', '202601000001')->count());
        $this->assertSame(2, CustomerPortalLink::query()->where('customer_id', $customer->id)->count());

        CustomerPortalLink::query()->where('user_id', $user->id)->update(['status' => 'approved']);
        $customer->update(['portal_approved' => true]);

        $this->post('/portal/login', ['email' => 'ali@newco.test', 'password' => 'secret123'])->assertRedirect('/portal/select-company');
        $this->get('/portal/select-company')->assertRedirect('/portal');
        $this->assertSame($customer->id, PortalSelection::customerId());
        $this->get('/portal')->assertOk()->assertSee('NewCo Sdn Bhd')->assertSee('+ New order')->assertDontSee('>Documents</a>', false);
    }

    public function test_password_reset_by_email_link(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $token = null;

        $this->post('/portal/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Mail::assertSent(SystemNotificationMail::class, function ($m) use (&$token) {
            preg_match('~reset-password/([^?\s]+)~', $m->body, $match);
            $token = $match[1] ?? null;

            return $token !== null;
        });

        $this->post('/portal/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect('/portal/login');
        $this->post('/portal/login', ['email' => $user->email, 'password' => 'newpass123'])->assertRedirect('/portal/select-company');
    }

    public function test_hidden_portal_pages_are_not_routed(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $this->actingAs($user);
        $this->get('/portal/select-company')->assertRedirect('/portal');

        foreach (['/portal/orders', '/portal/deliveries', '/portal/statement', '/portal/profile', '/portal/documents'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get('/portal/enquiry/create')->assertOk();
    }

    public function test_customer_submits_order_and_admin_badge_counts_it(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $customer = Customer::query()->findOrFail($user->approvedCustomerIds()->first());
        $this->actingAs($user);
        $this->get('/portal/select-company')->assertRedirect('/portal');

        \Illuminate\Support\Facades\Event::fake([\App\Events\PortalOrderSubmitted::class]);

        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'order_type' => 'cash',
            'service_type' => 'pickup',
            'payment_method' => 'bank_transfer',
            'customer_do_number' => 'DO-T-1',
            'pickup_address' => 'No. 1, Jalan Test',
            'destinations' => [['address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other']],
            'items' => [['item_name' => 'Carton', 'quantity' => 2]],
            'contacts' => [['name' => 'Ali', 'phone' => '012']],
        ])->assertSessionHasNoErrors()->assertRedirect('/portal');

        $enquiry = \App\Domains\Quotation\Models\PortalEnquiry::query()->latest('id')->firstOrFail();
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\PortalOrderSubmitted::class, fn ($e) => $e->enquiry->is($enquiry));
        $this->assertSame($customer->id, $enquiry->customer_id);
        $this->assertSame('Ali', $enquiry->payload['contacts'][0]['name']);
        $this->get('/portal')->assertOk()->assertSee($enquiry->order_number ?: $enquiry->reference_no);

        $this->actingAs(User::query()->where('is_hq', true)->firstOrFail());
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Filament\Facades\Filament::setTenant(\App\Domains\MasterData\Models\Company::query()->findOrFail($enquiry->company_id));
        $this->assertGreaterThanOrEqual(1, (int) \App\Filament\Pages\Orders::getNavigationBadge());

        $tenant = \Filament\Facades\Filament::getTenant();
        $this->getJson(route('filament.admin.new-portal-orders', ['tenant' => $tenant]))
            ->assertOk()
            ->assertJsonPath('latest_id', $enquiry->id)
            ->assertJsonPath('orders.0.number', $enquiry->order_number ?: $enquiry->reference_no);
        $this->get(\App\Filament\Pages\Orders::getUrl())->assertOk()
            ->assertSee('sweetalert2.all.min.js', false)
            ->assertSee('OgNewOrderAlert', false)
            ->assertSee('setInterval(run, 1000)', false);
        $this->get(\App\Filament\Pages\Dashboard::getUrl())->assertOk()->assertSee('OgNewOrderAlert', false);
    }

    public function test_admin_approves_registrations(): void
    {
        $admin = User::query()->where('is_hq', true)->first() ?? User::query()->role('hq_admin')->firstOrFail();
        $customer = Customer::query()->whereNotNull('company_id')->firstOrFail();
        $user = User::factory()->create(['email' => 'pending@x.test']);
        $user->customers()->attach($customer->id, ['status' => 'pending']);
        $link = CustomerPortalLink::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Filament\Facades\Filament::setTenant($customer->company);

        \Livewire\Livewire::test(\App\Filament\Pages\PortalAccessApprovals::class)
            ->assertCanSeeTableRecords([$link])
            ->callTableAction('approve', $link, ['remarks' => 'ok'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('approved', $link->fresh()->status);
        $this->assertTrue((bool) $customer->fresh()->portal_approved);
    }
}
