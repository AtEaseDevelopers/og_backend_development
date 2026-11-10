<?php

namespace Tests\Feature;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPortalLink;
use App\Domains\Quotation\Models\Quotation;
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
        $this->get('/portal')->assertOk()->assertSee('NewCo Sdn Bhd')->assertSee('+ New order')->assertSee('Documents', false);
    }

    /** An active salesperson of the KL branch. */
    private function salesperson(): User
    {
        $salesperson = User::factory()->create(['name' => 'Test Salesperson', 'email' => 'sales@link.test', 'is_active' => true]);
        $salesperson->assignRole('salesperson');
        $salesperson->branches()->syncWithoutDetaching([Branch::query()->where('code', 'KL')->firstOrFail()->id]);

        return $salesperson;
    }

    public function test_salesperson_register_link_sets_the_customers_salesperson(): void
    {
        $salesperson = $this->salesperson();
        $branch = $salesperson->branches()->orderBy('branches.id')->first() ?? Branch::query()->where('code', 'KL')->firstOrFail();
        $salesperson->branches()->syncWithoutDetaching([$branch->id]);

        // a visitor who is not logged in lands on the register page, with the salesperson and their branch selected
        $this->get(route('portal.salesperson-link', ['token' => $salesperson->ensureOrderingToken()]))->assertRedirect('/portal/register');
        $this->get('/portal/register')->assertOk()
            ->assertSee('Your O&amp;G salesperson (optional)', false)
            ->assertSee('<option value="'.$salesperson->id.'" selected>', false)
            ->assertSee('<option value="'.$branch->id.'" selected>', false);

        $this->post('/portal/register', [
            'name' => 'Siti PIC', 'email' => 'siti@linkco.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'LinkCo Sdn Bhd', 'brn' => '202601000777', 'phone' => '0123456780',
            'address' => '7 Jalan Link, KL', 'branch_id' => $branch->id, 'salesperson_id' => $salesperson->id,
        ])->assertRedirect('/portal/login');

        $customer = Customer::query()->where('brn', '202601000777')->firstOrFail();
        $this->assertSame($salesperson->id, (int) $customer->salesperson_id);

        // the salesperson is optional, and someone who is not a salesperson is not taken
        $this->post('/portal/register', [
            'name' => 'Abu PIC', 'email' => 'abu@plainco.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'PlainCo Sdn Bhd', 'brn' => '202601000778', 'phone' => '0123456781',
            'address' => '8 Jalan Plain, KL', 'branch_id' => $branch->id,
            'salesperson_id' => User::query()->where('email', 'portal@demo.local')->value('id'),
        ])->assertRedirect('/portal/login');
        $this->assertNull(Customer::query()->where('brn', '202601000778')->firstOrFail()->salesperson_id);
    }

    public function test_customers_salesperson_is_the_default_on_their_orders(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $customer = Customer::query()->findOrFail($user->approvedCustomerIds()->first());
        $salesperson = $this->salesperson();
        $customer->update(['salesperson_id' => $salesperson->id]);

        $this->actingAs($user, 'portal');
        $this->get('/portal/select-company')->assertRedirect('/portal');
        $this->get('/portal/enquiry/create')->assertOk()->assertSee('<option value="'.$salesperson->id.'" selected>', false);

        \Illuminate\Support\Facades\Event::fake([\App\Events\PortalOrderSubmitted::class]);
        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'pickup_address' => 'No. 1, Jalan Test',
            'destinations' => [['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other']],
            'items' => [['kind' => 'other', 'description' => 'A crate', 'quantity' => 1, 'length' => 50, 'width' => 40, 'height' => 40, 'weight' => 20]],
            'declaration' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect('/portal');

        $enquiry = \App\Domains\Quotation\Models\PortalEnquiry::query()->latest('id')->firstOrFail();
        $this->assertSame($salesperson->id, (int) $enquiry->salesperson_id);
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

    public function test_portal_full_menu_pages_are_routed(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $customer = Customer::query()->findOrFail($user->approvedCustomerIds()->first());
        $this->actingAs($user, 'portal');
        $this->get('/portal/select-company')->assertRedirect('/portal');

        foreach (['/portal/orders', '/portal/deliveries', '/portal/statement', '/portal/profile', '/portal/documents'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/portal/statement/pdf?month='.now()->format('Y-m'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $other = Quotation::query()->where('customer_id', '!=', $customer->id)->first();
        if ($other) {
            $this->get('/portal/quotations/'.$other->id)->assertNotFound();
        }

        $csn = ConsignmentNote::query()->where('customer_id', $customer->id)->whereHas('deliveryOrders')->first()
            ?? ConsignmentNote::query()->whereHas('deliveryOrders')->first();
        if ($csn) {
            $csn->update(['customer_id' => $customer->id]);
            $this->get('/portal/deliveries/'.$csn->id)->assertOk();
        }

        $this->get('/portal/enquiry/create')->assertOk()
            ->assertSee('+ Add another order', false)
            ->assertSee('id="order-template"', false)
            ->assertSee('function addOrder', false)
            ->assertSee('+ Add item', false)
            ->assertDontSee('Customer &amp; ownership', false)
            ->assertDontSee('PIC name', false)
            ->assertSee('1. Billing customer', false)
            ->assertSee('2. Pickup / Drop-off at store', false)
            ->assertSee('3. Delivery / Own collection and items', false)
            ->assertSee('4. Remark', false)
            ->assertSee('Pickup date', false)
            ->assertDontSee('Special handling', false)
            ->assertDontSee('Special requirements', false)
            ->assertDontSee('Required delivery date', false)
            ->assertSee(\App\Domains\Quotation\Models\PortalEnquiry::DECLARATION, false)
            ->assertDontSee('Received through', false)
            ->assertDontSee('Your DO number', false)
            ->assertDontSee('Payment method', false)
            ->assertDontSee('Order type', false)
            ->assertDontSee('Chartered lorry trip', false)
            ->assertSee('+ Add another drop-off address', false)
            ->assertSee('+ Add another pickup address', false);
    }

    public function test_customer_submits_order_and_admin_badge_counts_it(): void
    {
        $user = User::query()->where('email', 'portal@demo.local')->firstOrFail();
        $customer = Customer::query()->findOrFail($user->approvedCustomerIds()->first());
        $this->actingAs($user, 'portal');
        $this->get('/portal/select-company')->assertRedirect('/portal');

        \Illuminate\Support\Facades\Event::fake([\App\Events\PortalOrderSubmitted::class]);

        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'order_type' => 'cash',
            'service_type' => 'pickup',
            'payment_method' => 'bank_transfer',
            'customer_do_number' => 'DO-T-1',
            'pickup_address' => 'No. 1, Jalan Test',
            'extra_pickups' => [['address' => 'No. 2, Jalan Test']],
            'destinations' => [
                ['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other'],
                ['consignee_name' => 'Extra', 'address' => 'No. 9, Jalan Extra', 'drop_off_type' => 'other'],
            ],
            'items' => [['kind' => 'measurement', 'sub_category' => 'Carton', 'item_name' => 'Carton', 'quantity' => 2, 'length' => 30, 'width' => 30, 'height' => 30, 'weight' => 5]],
            'contacts' => [['name' => 'Ali', 'phone' => '012']],
            'declaration' => 1,
        ])->assertSessionHasErrors('orders.0.destinations');

        // the declaration, the size and weight of an item, and an item or its description are required
        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'pickup_address' => 'No. 1, Jalan Test',
            'destinations' => [['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other']],
            'items' => [['kind' => 'other', 'quantity' => 1]],
        ])->assertSessionHasErrors(['declaration']);

        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'pickup_address' => 'No. 1, Jalan Test',
            'destinations' => [['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other']],
            'items' => [['kind' => 'other', 'quantity' => 1]],
            'declaration' => 1,
        ])->assertSessionHasErrors(['orders.0.items.0.item_name', 'orders.0.items.0.length']);

        $this->post('/portal/enquiry', [
            'branch_id' => $customer->branch_id,
            'order_type' => 'cash',
            'service_type' => 'pickup',
            'payment_method' => 'bank_transfer',
            'pickup_address' => 'No. 1, Jalan Test',
            'pickup_date' => '2026-12-01',
            'destinations' => [
                ['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other'],
                ['consignee_name' => 'Extra', 'address' => 'No. 9, Jalan Extra', 'drop_off_type' => 'other'],
            ],
            'items' => [
                ['kind' => 'measurement', 'sub_category' => 'Carton', 'item_name' => 'Carton', 'quantity' => 2, 'length' => 30, 'width' => 30, 'height' => 30, 'weight' => 5, 'destination_index' => 0],
                ['kind' => 'other', 'description' => 'A crate of spare parts', 'quantity' => 1, 'length' => 50, 'width' => 40, 'height' => 40, 'weight' => 20, 'destination_index' => 1],
            ],
            'contacts' => [['name' => 'Ali', 'phone' => '012']],
            'declaration' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect('/portal');

        $enquiry = \App\Domains\Quotation\Models\PortalEnquiry::query()->latest('id')->firstOrFail();
        // the new form (client, 11 Nov 2026): several drop-offs for any goods, an item described only, a pickup date
        // (no delivery date) and the declaration
        $this->assertSame('multi_dropoff', $enquiry->payload['route_mode']);
        $this->assertSame('A crate of spare parts', $enquiry->payload['items'][1]['item_name']);
        $this->assertNull($enquiry->payload['items'][1]['kind']);
        $this->assertSame('2026-12-01', $enquiry->pickupDate()?->toDateString());
        $this->assertNull($enquiry->preferred_delivery_date);
        $this->assertNotNull($enquiry->declarationLine());
        $this->assertCount(2, app(\App\Domains\Quotation\Actions\CreateOrderFromEnquiry::class)->pairsFromPayload($enquiry));
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\PortalOrderSubmitted::class, fn ($e) => $e->enquiry->is($enquiry));
        $this->assertSame($customer->id, $enquiry->customer_id);
        // no customer fields on the form (4 Nov 2026): the logged-in customer's details are filled by the server
        $this->assertSame($customer->company_name, $enquiry->payload['customer_name']);
        $this->assertSame($customer->address, $enquiry->payload['customer_address']);
        $this->assertNull($enquiry->customer_do_number);
        $this->assertSame('Ali', $enquiry->payload['contacts'][0]['name']);
        $this->get('/portal')->assertOk()
            ->assertSee($enquiry->order_number ?: $enquiry->reference_no)
            ->assertSee(route('portal.enquiry.pdf', $enquiry), false);
        $this->get(route('portal.enquiry.pdf', $enquiry))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::query()->where('is_hq', true)->firstOrFail(), 'web');
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

        $this->actingAs($user, 'portal');
        $this->get('/portal/select-company')->assertRedirect('/portal');
        $before = \App\Domains\Quotation\Models\PortalEnquiry::query()->count();
        $this->post('/portal/enquiry', [
            'orders' => [
                [
                    'branch_id' => $customer->branch_id,
                    'order_type' => 'cash',
                    'service_type' => 'pickup',
                    'payment_method' => 'bank_transfer',
                    'pickup_address' => 'No. 1, Jalan Test',
                    'destinations' => [['consignee_name' => 'Contoh', 'address' => 'No. 8, Jalan Contoh', 'drop_off_type' => 'other']],
                    'items' => [['kind' => 'measurement', 'sub_category' => 'Carton', 'item_name' => 'Carton', 'quantity' => 2, 'length' => 30, 'width' => 30, 'height' => 30, 'weight' => 5]],
                ],
                [
                    'branch_id' => $customer->branch_id,
                    'order_type' => 'cash',
                    'service_type' => 'pickup',
                    'payment_method' => 'bank_transfer',
                    'pickup_address' => 'No. 2, Jalan Test',
                    'destinations' => [['consignee_name' => 'Contoh', 'address' => 'No. 10, Jalan Contoh', 'drop_off_type' => 'other']],
                    'items' => [['kind' => 'measurement', 'sub_category' => 'Carton', 'item_name' => 'Carton', 'quantity' => 1, 'length' => 30, 'width' => 30, 'height' => 30, 'weight' => 5]],
                ],
            ],
            'declaration' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect('/portal');
        $this->assertSame($before + 2, \App\Domains\Quotation\Models\PortalEnquiry::query()->count());
    }

    public function test_admin_approves_registrations(): void
    {
        $admin = User::query()->where('is_hq', true)->first() ?? User::query()->role('hq_admin')->firstOrFail();
        $customer = Customer::query()->whereNotNull('company_id')->firstOrFail();
        $user = User::factory()->create(['email' => 'pending@x.test']);
        $user->customers()->attach($customer->id, ['status' => 'pending']);
        $link = CustomerPortalLink::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($admin, 'web');
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
