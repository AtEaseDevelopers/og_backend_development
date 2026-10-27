<?php

namespace App\Filament\Resources\CustomerResource\Schemas;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;

class CustomerForm
{
    /**
     * One page, no tabs (user, 25 Oct 2026): a section per part, with every column of the client's Customer
     * Information workbook (debtor code to MSIC 3) as a field. The special prices are a tab on the view page only.
     */
    public static function configure(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => \App\Support\CurrentCompany::id())
                ->required(),
            Forms\Components\Hidden::make('branch_id')
                ->default(fn () => \App\Support\CurrentCompany::branchId())
                ->required(),

            static::section('Account', 'heroicon-o-identification', static::accountFields()),
            static::section('Address', 'heroicon-o-map-pin', static::addressFields()),
            static::section('Contact', 'heroicon-o-phone', static::contactFields()),
            static::section('Credit & statement', 'heroicon-o-banknotes', static::creditFields()),
            static::section('Tax & e-invoice', 'heroicon-o-receipt-percent', static::taxFields()),
            static::section('Notes', 'heroicon-o-chat-bubble-bottom-center-text', static::noteFields()),
            static::section('Order type & pricing consent', 'heroicon-o-document-check', static::consentFields())
                ->description('Credit-term customers may hold a consent letter so approved special prices proceed without another customer confirmation.'),
            static::section('Contact persons & delivery locations', 'heroicon-o-users', static::listFields())
                ->collapsible()
                ->collapsed(fn (string $operation) => $operation === 'edit'),
            static::section('Customer portal access', 'heroicon-o-key', static::portalLoginFields())
                ->description('Creates a login for the customer portal (/portal).')
                ->visibleOn('create'),
        ]);
    }

    /** @param  list<Forms\Components\Component>  $schema */
    private static function section(string $heading, string $icon, array $schema): Forms\Components\Section
    {
        return Forms\Components\Section::make($heading)->icon($icon)->schema($schema)->columnSpanFull();
    }

    /** @return list<Forms\Components\Component> */
    public static function accountFields(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Debtor code')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate.'),
                Forms\Components\TextInput::make('company_name')
                    ->label('Company name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('brn')
                    ->label('Company registration no. (BRN)')
                    ->maxLength(50),
                Forms\Components\TextInput::make('area')
                    ->label('Area code')
                    ->maxLength(100),
                Forms\Components\TextInput::make('debtor_type')
                    ->label('Debtor type')
                    ->maxLength(50)
                    ->helperText('As in AutoCount, e.g. TD.'),
                Forms\Components\TextInput::make('control_account')
                    ->label('Control account')
                    ->placeholder('300-D001')
                    ->maxLength(50),
                Forms\Components\Select::make('salesperson_id')
                    ->label('Agent')
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'salesperson', 'branch_manager', 'counter', 'finance', 'super_admin',
                        ]))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Forms\Components\Placeholder::make('branch_label')
                    ->label('O&G company')
                    ->content(fn () => \App\Support\CurrentCompany::get()?->getFilamentName() ?? '—'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->required(),
                Forms\Components\Toggle::make('is_group_company')
                    ->label('Group company')
                    ->default(false)
                    ->inline(false),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function addressFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Group::make([
                    Forms\Components\Textarea::make('address')
                        ->label('Address (address 1 – 4)')
                        ->rows(4),
                    Forms\Components\TextInput::make('postcode')->label('Postcode')->maxLength(20),
                ]),
                Forms\Components\Group::make([
                    Forms\Components\Textarea::make('delivery_address_text')
                        ->label('Delivery address (deliver address 1 – 4)')
                        ->rows(4)
                        ->dehydrated(false),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('delivery_postcode')
                            ->label('Deliver postcode')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('delivery_state')
                            ->label('State')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('delivery_city')
                            ->label('City')
                            ->dehydrated(false),
                    ]),
                ]),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function contactFields(): array
    {
        return [
            // grouped for the user, not in the workbook's order (user, 25 Oct 2026): people, then numbers, then online
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('attention')->label('Attention')->maxLength(255),
                Forms\Components\TextInput::make('contact_person_3')->label('Contact person 1')->maxLength(150),
                Forms\Components\TextInput::make('contact_person_4')->label('Contact person 2')->maxLength(150),
                Forms\Components\TextInput::make('business_nature')->label('Business nature')->maxLength(255),
                Forms\Components\TextInput::make('phone')->label('Phone 1')->tel()->maxLength(50),
                Forms\Components\TextInput::make('phone_2')->label('Phone 2')->tel()->maxLength(50),
                Forms\Components\TextInput::make('phone_3')->label('Phone 3')->tel()->maxLength(50),
                Forms\Components\TextInput::make('phone_4')->label('Phone 4')->tel()->maxLength(50),
                Forms\Components\TextInput::make('mobile')->label('Mobile')->tel()->maxLength(50),
                Forms\Components\TextInput::make('fax')->label('Fax')->tel()->maxLength(50),
                Forms\Components\TextInput::make('email')->label('Email address')->email()->maxLength(255),
                Forms\Components\TextInput::make('website')->label('Website')->url()->maxLength(255),
                Forms\Components\Toggle::make('email_notifications')
                    ->label('Email notifications')
                    ->default(true)
                    ->inline(false),
                Forms\Components\Toggle::make('portal_approved')
                    ->label('Portal approved')
                    ->default(false)
                    ->inline(false)
                    ->visibleOn('edit'),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function creditFields(): array
    {
        return [
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('display_term')
                    ->label('Display term')
                    ->placeholder('30 DAYS')
                    ->maxLength(50),
                Forms\Components\Select::make('credit_term_days')
                    ->label('Credit term')
                    ->options(static::creditTermOptions())
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('aging_on')
                    ->label('Aging on')
                    ->options([
                        'invoice_date' => 'Invoice date (I)',
                        'due_date' => 'Due date (D)',
                    ])
                    ->default('due_date')
                    ->required(),
                Forms\Components\Select::make('statement_type')
                    ->label('Statement type')
                    ->options([
                        'open_item' => 'Open item (O)',
                        'balance_forward' => 'Balance forward (B)',
                        'no_statement' => 'No statement (N)',
                    ])
                    ->default('open_item')
                    ->required(),
                Forms\Components\Select::make('currency')
                    ->options(static::currencyOptions())
                    ->default('MYR')
                    ->required(),
                Forms\Components\Toggle::make('is_credit')
                    ->label('Credit customer')
                    ->default(false)
                    ->inline(false)
                    ->live(),
                Forms\Components\TextInput::make('credit_limit')
                    ->numeric()
                    ->prefix('RM')
                    ->default(0)
                    ->visible(fn (Get $get): bool => (bool) $get('is_credit')),
                Forms\Components\TextInput::make('credit_overdue_limit')
                    ->label('Credit term overdue limit')
                    ->numeric()
                    ->prefix('RM')
                    ->visible(fn (Get $get): bool => (bool) $get('is_credit')),
                Forms\Components\Select::make('credit_control')
                    ->options(static::creditControlOptions())
                    ->default('controlled_by_credit_term')
                    ->visible(fn (Get $get): bool => (bool) $get('is_credit')),
                Forms\Components\Select::make('credit_control_scope')
                    ->label('Apply credit control on')
                    ->options([
                        'all_documents' => 'All documents',
                        'per_document' => 'Different per document',
                    ])
                    ->default('all_documents')
                    ->visible(fn (Get $get): bool => (bool) $get('is_credit')),
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Discount %')
                    ->numeric()
                    ->suffix('%'),
                Forms\Components\TextInput::make('price_category')
                    ->label('Price category')
                    ->maxLength(50),
                Forms\Components\TextInput::make('account_group')
                    ->label('Account group')
                    ->maxLength(50),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function taxFields(): array
    {
        return [
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('gst_no')->label('GST no.')->maxLength(50),
                Forms\Components\TextInput::make('sst_registration_no')
                    ->label('SST no.')
                    ->maxLength(50),
                Forms\Components\TextInput::make('tax_type')
                    ->label('Tax code')
                    ->placeholder('SV-6')
                    ->maxLength(50),
                Forms\Components\TextInput::make('tin')
                    ->label('TIN no.')
                    ->maxLength(50)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('einvoice_tin', $state)),
                Forms\Components\TextInput::make('msic_code')->label('MSIC 1')->maxLength(20),
                Forms\Components\TextInput::make('msic_code_2')->label('MSIC 2')->maxLength(20),
                Forms\Components\TextInput::make('msic_code_3')->label('MSIC 3')->maxLength(20),
                Forms\Components\TextInput::make('einvoice_tin')
                    ->label('E-invoice TIN')
                    ->maxLength(50),
                Forms\Components\Select::make('business_type')
                    ->options([
                        'company' => 'Company',
                        'individual' => 'Individual',
                        'government' => 'Government',
                        'foreign' => 'Foreign buyer',
                    ])
                    ->searchable(),
                Forms\Components\Select::make('einvoice_id_type')
                    ->label('ID type')
                    ->options([
                        'BRN' => 'BRN',
                        'NRIC' => 'NRIC',
                        'PASSPORT' => 'Passport',
                        'ARMY' => 'Army ID',
                    ]),
                Forms\Components\TextInput::make('einvoice_id_value')
                    ->label('ID value')
                    ->maxLength(50)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state, Get $get) {
                        if (($get('einvoice_id_type') ?? 'BRN') === 'BRN') {
                            $set('brn', $state);
                        }
                    }),
                Forms\Components\TextInput::make('einvoice_buyer_name')
                    ->label('Buyer name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sales_tax_exemption_no')
                    ->label('Sales tax exemption no.')
                    ->maxLength(100),
                Forms\Components\DatePicker::make('sales_tax_exemption_expiry')
                    ->label('Exemption expiry'),
                Forms\Components\Textarea::make('einvoice_address')
                    ->label('E-invoice address')
                    ->rows(2)
                    ->columnSpan(2),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function noteFields(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Textarea::make('own_note')
                    ->label('Own note: other email, statement remark')
                    ->rows(2),
                Forms\Components\Textarea::make('mailing_statement_remark')
                    ->label('Mailing statement remark')
                    ->rows(2),
                Forms\Components\Textarea::make('payment_note')
                    ->label('Own note (payment)')
                    ->rows(2),
            ]),
            Forms\Components\Textarea::make('notes')
                ->label('Note for documents')
                ->rows(2)
                ->helperText('Can be copied to quotation / invoice documents involving this customer.'),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function consentFields(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('default_order_type')
                    ->label('Default order type')
                    ->options(\App\Enums\OrderType::options())
                    ->helperText('Term → Term / Cash / COD · COD → Cash only · Cash cannot change'),
                Forms\Components\Select::make('consent_letter_type')
                    ->label('Consent letter')
                    ->options(\App\Domains\MasterData\Models\Customer::consentOptions())
                    ->default(\App\Domains\MasterData\Models\Customer::CONSENT_NONE)
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set(
                        'pricing_reconfirmation_required',
                        $state !== \App\Domains\MasterData\Models\Customer::CONSENT_NO_RECONFIRMATION,
                    )),
                Forms\Components\Toggle::make('pricing_reconfirmation_required')
                    ->label('Pricing reconfirmation required')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Off = quotation at the agreed special price is accepted automatically.'),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\DatePicker::make('consent_valid_from')->label('Consent valid from'),
                Forms\Components\DatePicker::make('consent_valid_until')->label('Consent valid until'),
                Forms\Components\FileUpload::make('consent_document_path')
                    ->label('Consent letter document')
                    ->disk('public')
                    ->directory('consent-letters')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->downloadable()
                    ->openable(),
            ])->visible(fn (Get $get): bool => $get('consent_letter_type') !== \App\Domains\MasterData\Models\Customer::CONSENT_NONE && filled($get('consent_letter_type'))),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function listFields(): array
    {
        return [
            Forms\Components\Repeater::make('pics')
                ->label('Other contact persons')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\Toggle::make('is_default')->label('Default contact'),
                ])
                ->columns(4)
                ->defaultItems(0)
                ->addActionLabel('Add contact person')
                ->columnSpanFull(),
            // the customer's own branches / sites (not O&G's)
            Forms\Components\Repeater::make('branches')
                ->label('Customer branch / delivery locations')
                ->relationship('addresses', modifyQueryUsing: fn ($query) => $query->where('type', 'branch'))
                ->schema([
                    Forms\Components\Hidden::make('type')->default('branch'),
                    Forms\Components\TextInput::make('label')
                        ->label('Location name')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Textarea::make('address')->required()->rows(2),
                    Forms\Components\TextInput::make('postcode')->maxLength(20),
                    Forms\Components\TextInput::make('state')->maxLength(100),
                    Forms\Components\TextInput::make('city')->maxLength(100),
                    Forms\Components\TextInput::make('google_maps_url')
                        ->label('Google Maps URL')
                        ->url()
                        ->maxLength(500),
                    Forms\Components\Toggle::make('is_default')->label('Default location'),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add location')
                ->columnSpanFull(),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function portalLoginFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('portal_user_name')
                    ->label('Login name')
                    ->maxLength(255)
                    ->dehydrated(false),
                Forms\Components\TextInput::make('portal_email')
                    ->label('Login email')
                    ->email()
                    ->maxLength(255)
                    ->rule('unique:users,email')
                    ->dehydrated(false),
                Forms\Components\TextInput::make('portal_password')
                    ->label('Password')
                    ->password()
                    ->minLength(8)
                    ->confirmed()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('portal_password_confirmation')
                    ->label('Confirm password')
                    ->password()
                    ->dehydrated(false),
            ]),
        ];
    }

    /** @return array<string, string> */
    public static function debtorTypeOptions(): array
    {
        return [
            'corporate' => 'Corporate',
            'individual' => 'Individual',
            'government' => 'Government',
            'related_party' => 'Related party',
            'export' => 'Export',
        ];
    }

    /** @return array<int|string, string> */
    public static function creditTermOptions(): array
    {
        return [
            0 => 'C.O.D.',
            7 => '7 days',
            14 => '14 days',
            30 => '30 days',
            45 => '45 days',
            60 => '60 days',
            90 => '90 days',
        ];
    }

    /** @return array<string, string> */
    public static function creditControlOptions(): array
    {
        return [
            'all_disabled' => 'All disabled',
            'controlled_by_credit_term' => 'Controlled by credit term',
            'no_block' => 'No block (warn only)',
            'block' => 'Block',
            'need_password' => 'Need password',
            'suspend' => 'Suspend',
        ];
    }

    /** @return array<string, string> */
    public static function currencyOptions(): array
    {
        return [
            'MYR' => 'MYR — Malaysian Ringgit',
            'USD' => 'USD — US Dollar',
        ];
    }

    public static function generateDebtorCode(?string $companyName): string
    {
        $prefix = Str::upper(Str::limit(Str::slug($companyName ?? 'DEB', ''), 6, ''));

        if ($prefix === '') {
            $prefix = 'DEB';
        }

        return $prefix.'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}
