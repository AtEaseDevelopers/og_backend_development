<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Models\User;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The customer form and page follow the client's "Customer Master Data V2" (user, 16 Nov 2026): ACCOUNT, CREDIT TERM
 * and NOTES; the delivery address and the fields the document dropped are gone from the form.
 */
class CustomerMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_customer_form_has_the_documents_fields_and_debtor_type_decides_credit(): void
    {
        $this->seed();
        Permissions::flush();

        $admin = User::query()->where('is_hq', true)->firstOrFail();
        $customer = Customer::query()->whereNotNull('company_id')->firstOrFail();
        $customer->forceFill(['default_order_type' => 'cash', 'is_credit' => false])->save();

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($customer->company);

        // the fields of the document are on the form; the ones it dropped are not
        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertFormFieldExists('code')
            ->assertFormFieldExists('default_order_type')
            ->assertFormFieldExists('company_name')
            ->assertFormFieldExists('brn')
            ->assertFormFieldExists('address')
            ->assertFormFieldExists('postcode')
            ->assertFormFieldExists('mobile')
            ->assertFormFieldExists('email')
            ->assertFormFieldExists('gst_no')
            ->assertFormFieldExists('sst_registration_no')
            ->assertFormFieldExists('tax_type')
            ->assertFormFieldExists('tin')
            ->assertFormFieldExists('msic_code')
            ->assertFormFieldExists('msic_code_2')
            ->assertFormFieldExists('msic_code_3')
            ->assertFormFieldExists('credit_term_days')
            ->assertFormFieldExists('credit_limit')
            ->assertFormFieldExists('aging_on')
            ->assertFormFieldExists('statement_type')
            ->assertFormFieldExists('account_opening_date')
            ->assertFormFieldExists('mailing_address')
            ->assertFormFieldExists('mailing_statement_remark')
            ->assertFormFieldExists('own_note')
            ->assertFormFieldDoesNotExist('delivery_address_text')
            ->assertFormFieldDoesNotExist('delivery_postcode')
            ->assertFormFieldDoesNotExist('area')
            ->assertFormFieldDoesNotExist('control_account')
            ->assertFormFieldDoesNotExist('is_group_company')
            ->assertFormFieldDoesNotExist('display_term')
            ->assertFormFieldDoesNotExist('currency')
            // kept although not in the document: used by the e-invoice submission and the portal profile
            ->assertFormFieldExists('einvoice_buyer_name')
            ->assertFormSet(['default_order_type' => 'cash'])
            // Debtor type Credit makes it a credit customer; the new note fields are saved
            ->fillForm([
                'default_order_type' => 'term',
                'credit_limit' => 50000,
                'account_opening_date' => '2026-01-15',
                'mailing_address' => 'PO Box 12, 50000 Kuala Lumpur',
                'own_note' => 'Calls before delivery',
                'mobile' => '012-345 6789',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer = $customer->fresh();
        $this->assertSame('term', $customer->default_order_type);
        $this->assertTrue((bool) $customer->is_credit);
        $this->assertSame('2026-01-15', $customer->account_opening_date->toDateString());
        $this->assertSame('PO Box 12, 50000 Kuala Lumpur', $customer->mailing_address);
        $this->assertSame('Calls before delivery', $customer->own_note);
        $this->assertSame('012-345 6789', $customer->mobile);

        // back to COD: no longer a credit customer
        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['default_order_type' => 'cod'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse((bool) $customer->fresh()->is_credit);

        // the customer page shows the same groups
        Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertSee('Debtor account code')
            ->assertSee('Debtor type')
            ->assertSee('Billing address')
            ->assertSee('Credit term')
            ->assertSee('Account opening date')
            ->assertSee('Mailing address')
            ->assertSee('Internal office note')
            ->assertDontSee('Delivery address')
            ->assertDontSee('Control account');

        // a new customer needs only its name
        Livewire::test(CreateCustomer::class)
            ->fillForm(['company_name' => 'New Debtor Sdn Bhd', 'default_order_type' => 'term'])
            ->call('create')
            ->assertHasNoFormErrors();
        $created = Customer::query()->where('company_name', 'New Debtor Sdn Bhd')->firstOrFail();
        $this->assertNotEmpty($created->code);
        $this->assertTrue((bool) $created->is_credit);
    }
}
