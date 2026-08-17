<?php

namespace App\Filament\Resources\CustomerResource\Schemas;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Str;

class CustomerForm
{
    public static function configure(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => \App\Support\CurrentCompany::id())
                ->required(),
            Forms\Components\Hidden::make('branch_id')
                ->default(fn () => \App\Support\CurrentCompany::branchId())
                ->required(),

            Forms\Components\Tabs::make('Customer')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Account')
                        ->schema(static::accountTab()),
                    Forms\Components\Tabs\Tab::make('General')
                        ->schema(static::generalTab()),
                    Forms\Components\Tabs\Tab::make('Contact')
                        ->schema(static::contactTab()),
                    Forms\Components\Tabs\Tab::make('Branches')
                        ->schema(static::branchesTab()),
                    Forms\Components\Tabs\Tab::make('E-Invoice')
                        ->schema(static::einvoiceTab()),
                    Forms\Components\Tabs\Tab::make('Portal login')
                        ->schema(static::portalLoginTab())
                        ->visibleOn('create'),
                    Forms\Components\Tabs\Tab::make('Others')
                        ->schema(static::othersTab()),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return list<Forms\Components\Component> */
    private static function accountTab(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Placeholder::make('branch_label')
                    ->label('Company')
                    ->content(fn () => \App\Support\CurrentCompany::get()?->getFilamentName() ?? '—'),
                Forms\Components\TextInput::make('control_account')
                    ->label('Control account')
                    ->placeholder('300-D001')
                    ->maxLength(50),
                Forms\Components\Select::make('debtor_type')
                    ->label('Debtor type')
                    ->options(static::debtorTypeOptions())
                    ->searchable(),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('company_name')
                    ->label('Company name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('code')
                    ->label('Debtor account')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate.'),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('brn')
                    ->label('Registration no.')
                    ->maxLength(50),
                Forms\Components\Select::make('status')
                    ->label('Active')
                    ->options(['active' => 'Yes', 'inactive' => 'No'])
                    ->default('active')
                    ->required(),
                Forms\Components\Toggle::make('is_group_company')
                    ->label('Group company')
                    ->default(false),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function generalTab(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('address')
                    ->label('Billing address')
                    ->rows(3),
                Forms\Components\Group::make([
                    Forms\Components\Textarea::make('delivery_address_text')
                        ->label('Delivery address')
                        ->rows(2)
                        ->dehydrated(false),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('delivery_postcode')
                            ->label('Postcode')
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
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('area')->maxLength(100),
                Forms\Components\TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('website')
                    ->url()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                Forms\Components\TextInput::make('fax')->tel()->maxLength(50),
                Forms\Components\TextInput::make('attention')->maxLength(255),
                Forms\Components\TextInput::make('business_nature')->maxLength(255),
                Forms\Components\Select::make('salesperson_id')
                    ->label('Agent')
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'salesperson', 'branch_manager', 'counter', 'finance', 'hq_admin',
                        ]))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('currency')
                    ->options(static::currencyOptions())
                    ->default('MYR')
                    ->required(),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('statement_type')
                    ->options([
                        'open_item' => 'Open item',
                        'balance_forward' => 'Balance forward',
                        'no_statement' => 'No statement',
                    ])
                    ->default('open_item')
                    ->required(),
                Forms\Components\Select::make('aging_on')
                    ->label('Aging on')
                    ->options([
                        'invoice_date' => 'Invoice date',
                        'due_date' => 'Due date',
                    ])
                    ->default('due_date')
                    ->required(),
                Forms\Components\Select::make('credit_term_days')
                    ->label('Credit term')
                    ->options(static::creditTermOptions())
                    ->default(0)
                    ->required(),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_credit')
                    ->label('Credit customer')
                    ->default(false)
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
            ]),
            Forms\Components\Grid::make(2)->schema([
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
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('portal_approved')
                    ->label('Portal approved')
                    ->default(false)
                    ->visibleOn('edit'),
                Forms\Components\Toggle::make('email_notifications')
                    ->label('Email notifications')
                    ->default(true),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function portalLoginTab(): array
    {
        return [
            Forms\Components\Section::make('Customer portal access')
                ->description('Creates a login for the customer portal (/portal). After sign-in the customer chooses branch and company.')
                ->schema([
                    Forms\Components\TextInput::make('portal_user_name')
                        ->label('Login name')
                        ->required()
                        ->maxLength(255)
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('portal_email')
                        ->label('Login email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rule('unique:users,email')
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('portal_password')
                        ->label('Password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->confirmed()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('portal_password_confirmation')
                        ->label('Confirm password')
                        ->password()
                        ->dehydrated(false),
                    Forms\Components\Toggle::make('portal_approved')
                        ->label('Portal approved')
                        ->default(true)
                        ->helperText('If off, the customer cannot sign in until approved.'),
                ])
                ->columns(2),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function contactTab(): array
    {
        return [
            Forms\Components\Repeater::make('pics')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\Toggle::make('is_default')->label('Default contact'),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel('Add contact')
                ->columnSpanFull(),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function branchesTab(): array
    {
        return [
            Forms\Components\Repeater::make('branches')
                ->label('Branch / delivery locations')
                ->relationship('addresses', modifyQueryUsing: fn ($query) => $query->where('type', 'branch'))
                ->schema([
                    Forms\Components\Hidden::make('type')->default('branch'),
                    Forms\Components\TextInput::make('label')
                        ->label('Branch name')
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
                    Forms\Components\Toggle::make('is_default')->label('Default branch'),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add branch')
                ->columnSpanFull(),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function einvoiceTab(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('tin')
                    ->label('TIN')
                    ->maxLength(50)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('einvoice_tin', $state)),
                Forms\Components\TextInput::make('einvoice_tin')
                    ->label('E-invoice TIN')
                    ->maxLength(50),
                Forms\Components\TextInput::make('sst_registration_no')
                    ->label('SST registration no.')
                    ->maxLength(50),
                Forms\Components\TextInput::make('msic_code')
                    ->label('MSIC code')
                    ->maxLength(20),
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
                    ->maxLength(255)
                    ->columnSpan(2),
            ]),
            Forms\Components\Textarea::make('einvoice_address')
                ->label('E-invoice address')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function othersTab(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('sales_tax_exemption_no')
                    ->label('Sales tax exemption no.')
                    ->maxLength(100),
                Forms\Components\DatePicker::make('sales_tax_exemption_expiry')
                    ->label('Exemption expiry'),
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Discount %')
                    ->numeric()
                    ->suffix('%'),
                Forms\Components\TextInput::make('tax_type')
                    ->label('Tax type')
                    ->maxLength(50),
                Forms\Components\TextInput::make('price_category')
                    ->label('Price category')
                    ->maxLength(50),
                Forms\Components\TextInput::make('account_group')
                    ->label('Account group')
                    ->maxLength(50),
            ]),
            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->rows(4)
                ->columnSpanFull()
                ->helperText('Notes can be copied to quotation / invoice documents involving this customer.'),
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
            'SGD' => 'SGD — Singapore Dollar',
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
