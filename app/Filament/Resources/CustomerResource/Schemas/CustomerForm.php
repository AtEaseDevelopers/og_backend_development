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
     * The customer form in the client's "Customer Master Data V2" (user, 16 Nov 2026): three groups — ACCOUNT (debtor
     * account code, debtor type Cash / Credit / COD, company name and registration no., billing address and postcode,
     * attention and phones, email, GST / SST / tax code / TIN / MSIC), CREDIT TERM (credit term, credit limit, aging on,
     * statement type) and NOTES (account opening date, mailing address, mailing statement remarks, internal office
     * note) — laid out several fields a row so the page needs little scrolling. The few things the system itself needs
     * and the document does not list (status, salesperson, charge SST, the portal switches) sit in the same rows;
     * pricing consent, the customer's own locations and the portal login are closed sections at the end. Fields the
     * document dropped (area code, control account, group company, delivery address, fax, website, display term,
     * currency, credit control, discount, e-invoice buyer details …) are no longer on the form; their stored values
     * stay, and the e-invoice falls back to the company name, TIN, registration no. and billing address.
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

            // one panel as the client's AutoCount screen (16 Nov 2026), not a card per part: the account on top, the
            // address at the left and the contact at the right, then tax, credit term and notes under thin rules —
            // the same fields, only laid out differently
            static::section('Customer', 'heroicon-o-identification', [
                ...static::accountFields(),
                static::rule(),
                ...static::contactFields(),
                static::rule('Tax'),
                ...static::taxFields(),
                static::rule('Credit term'),
                ...static::creditFields(),
                static::rule('Notes'),
                ...static::noteFields(),
            ]),
            // not in the document, but used by the e-invoice sent to LHDN and by the customer's own portal profile
            // (user, 16 Nov 2026: a field used elsewhere stays): a closed section; blank = the account's own details
            static::section('E-invoice buyer (MyInvois)', 'heroicon-o-receipt-percent', static::einvoiceFields())
                ->description('Leave blank to use the company name, TIN no., registration no. and billing address above.')
                ->collapsible()
                ->collapsed(),
            static::section('Pricing consent', 'heroicon-o-document-check', static::consentFields())
                ->description('A credit customer may hold a consent letter so approved special prices proceed without another customer confirmation.')
                ->collapsible()
                ->collapsed(),
            static::section('Customer branch / delivery locations', 'heroicon-o-users', static::listFields())
                ->collapsible()
                ->collapsed(),
            static::section('Customer portal access', 'heroicon-o-key', static::portalLoginFields())
                ->description('Creates a login for the customer portal (/portal).')
                ->collapsible()
                ->collapsed()
                ->visibleOn('create'),
        ]);
    }

    /** @param  list<Forms\Components\Component>  $schema */
    private static function section(string $heading, string $icon, array $schema): Forms\Components\Section
    {
        return Forms\Components\Section::make($heading)->icon($icon)->schema($schema)->compact()->columnSpanFull();
    }

    /** A thin rule between the parts of the one panel, with the part's name when it has one. */
    private static function rule(?string $title = null): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('rule_'.md5((string) $title))
            ->hiddenLabel()
            ->content(new \Illuminate\Support\HtmlString(
                '<div style="border-top:1px solid rgb(229 231 235);margin:.1rem 0 -.35rem;padding-top:.45rem;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:rgb(100 116 139)">'.e((string) $title).'</div>'
            ))
            ->columnSpanFull();
    }

    /** Debtor type (the document's Cash / Credit / COD): the customer's order type; Credit makes it a credit customer. */
    public static function debtorTypes(): array
    {
        return ['cash' => 'Cash', 'term' => 'Credit', 'cod' => 'COD'];
    }

    /**
     * The identity of the account and the settings the system needs beside it.
     *
     * @return list<Forms\Components\Component>
     */
    public static function accountFields(): array
    {
        return [
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Debtor account code')
                    ->placeholder('Blank = auto')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('company_name')
                    ->label('Company name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(5),
                Forms\Components\TextInput::make('brn')
                    ->label('Company registration no.')
                    ->maxLength(50)
                    ->columnSpan(3),
                // Cash / Credit / COD (the document): kept as the customer's order type; Credit = a credit customer
                // (Customer::booted keeps is_credit in step)
                Forms\Components\Select::make('default_order_type')
                    ->label('Debtor type')
                    ->options(static::debtorTypes())
                    ->default('cash')
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('salesperson_id')
                    ->label('Salesperson')
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'salesperson', 'branch_manager', 'counter', 'finance', 'super_admin',
                        ]))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\Placeholder::make('branch_label')
                    ->label('O&G company')
                    ->content(fn () => \App\Support\CurrentCompany::get()?->getFilamentName() ?? '—')
                    ->columnSpan(3),
                Forms\Components\Toggle::make('email_notifications')
                    ->label('Email notifications')
                    ->default(true)
                    ->inline(false)
                    ->columnSpan(2),
                Forms\Components\Toggle::make('portal_approved')
                    ->label('Portal approved')
                    ->default(false)
                    ->inline(false)
                    ->visibleOn('edit')
                    ->columnSpan(2),
            ]),
        ];
    }

    /**
     * Billing address and contact: the billing address and postcode, mobile and email, and the list of attention
     * names with their phones (the first row is the Attention and Phone 1 of the document, the second Phone 2; the
     * first is the default on new orders). $relationship false: a plain list the caller saves (the view page's card).
     *
     * @return list<Forms\Components\Component>
     */
    public static function contactFields(bool $relationship = true): array
    {
        // shown as a table (user, 10 Nov 2026): a header row, one line per PIC with its remove button, and only the
        // rows scroll when the list grows, so the window does not get longer
        $head = Forms\Components\Placeholder::make('pic_table_head')
            ->label('Attention & phone')
            ->content(new \Illuminate\Support\HtmlString(<<<'HTML'
                <style>
                    .og-pic-head { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; padding: .45rem 2.75rem .45rem .75rem; margin-bottom: -1.5rem;
                        font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139);
                        background: rgb(248 250 252); border: 1px solid rgb(229 231 235); border-bottom: 0; border-radius: .6rem .6rem 0 0; }
                    .og-pic-table > ul { max-height: 15.5rem; overflow-y: auto; border: 1px solid rgb(229 231 235); border-radius: 0 0 .6rem .6rem; }
                    .og-pic-table > ul > div { gap: 0 !important; }
                    .og-pic-table li.fi-fo-repeater-item { position: relative; border-radius: 0; box-shadow: none; --tw-ring-shadow: 0 0 #0000; --tw-ring-offset-shadow: 0 0 #0000; border-top: 1px solid rgb(241 245 249); }
                    .og-pic-table li.fi-fo-repeater-item:first-child { border-top: 0; }
                    .og-pic-table .fi-fo-repeater-item-header { position: absolute; right: .35rem; top: 50%; transform: translateY(-50%); padding: 0; border: 0; z-index: 1; }
                    .og-pic-table .fi-fo-repeater-item-content { padding: .4rem 2.75rem .4rem .75rem; border-top: 0 !important; }
                    .og-pic-table .fi-fo-repeater-item-content .fi-fo-component-ctn { gap: .75rem; }
                    .og-pic-table .fi-fo-repeater-item-content .fi-fo-field-wrp-label { display: none; }
                    .dark .og-pic-head { background: rgb(31 41 55); border-color: rgb(55 65 81); color: rgb(148 163 184); }
                    .dark .og-pic-table > ul { border-color: rgb(55 65 81); }
                    .dark .og-pic-table li.fi-fo-repeater-item { border-top-color: rgb(55 65 81); }
                </style>
                <div class="og-pic-head"><span>Attention</span><span>Phone</span></div>
                HTML))
            ->columnSpanFull();

        $pics = Forms\Components\Repeater::make('pics')
            ->hiddenLabel()
            ->schema([
                ...($relationship ? [] : [Forms\Components\Hidden::make('id')]),
                Forms\Components\TextInput::make('name')->label('Attention')->placeholder('Attention')->maxLength(255),
                Forms\Components\TextInput::make('phone')->label('Phone')->placeholder('Phone')->tel()->maxLength(50),
            ])
            ->columns(2)
            ->defaultItems(1)
            ->reorderable(false)
            ->addActionLabel('+ Add phone')
            ->helperText('Row 1 = Attention and Phone 1, row 2 = Phone 2. The first is the default on new orders.')
            ->extraAttributes(['class' => 'og-pic-table'])
            ->columnSpanFull();

        if ($relationship) {
            $pics->relationship('pics', fn ($query) => $query->orderByDesc('is_default')->orderBy('id'));
        }

        return [
            // two columns as the client's AutoCount screen (16 Nov 2026): the address at the left, the people and
            // numbers to reach at the right
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Group::make([
                    Forms\Components\Textarea::make('address')->label('Billing address')->rows(5),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('postcode')->label('Postcode')->maxLength(20),
                    ]),
                ]),
                Forms\Components\Group::make([
                    Forms\Components\Group::make([$head, $pics]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('mobile')->label('Mobile no.')->tel()->maxLength(50),
                        Forms\Components\TextInput::make('email')->label('Email address')->email()->maxLength(255),
                    ]),
                ]),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function taxFields(): array
    {
        return [
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\TextInput::make('gst_no')->label('GST no.')->maxLength(50),
                Forms\Components\TextInput::make('sst_registration_no')->label('SST no.')->maxLength(50),
                Forms\Components\TextInput::make('tax_type')
                    ->label('SST tax code')
                    ->placeholder('SV-6')
                    ->maxLength(50),
                Forms\Components\TextInput::make('tin')->label('TIN no.')->maxLength(50),
                Forms\Components\TextInput::make('msic_code')->label('MSIC 1')->maxLength(20),
                Forms\Components\TextInput::make('msic_code_2')->label('MSIC 2')->maxLength(20),
                Forms\Components\TextInput::make('msic_code_3')->label('MSIC 3')->maxLength(20),
                // the customer's orders carry SST (user, 3 Nov 2026): Order Quotation, Proforma, Cash Bill / Invoice
                Forms\Components\Toggle::make('charges_sst')
                    ->label(fn () => 'Charge SST '.rtrim(rtrim(number_format((float) config('og.sst_rate', 6), 2, '.', ''), '0'), '.').'% on orders')
                    ->default(false)
                    ->inline(false),
            ]),
        ];
    }

    /**
     * The buyer details of the e-invoice (MyInvois): used by the submission to LHDN and edited by the customer in the
     * portal profile. Blank ones fall back to the account's own details.
     *
     * @return list<Forms\Components\Component>
     */
    public static function einvoiceFields(): array
    {
        return [
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\TextInput::make('einvoice_buyer_name')->label('Buyer name')->maxLength(255)->columnSpan(4),
                Forms\Components\TextInput::make('einvoice_tin')->label('Buyer TIN')->maxLength(50)->columnSpan(2),
                Forms\Components\Select::make('einvoice_id_type')
                    ->label('ID type')
                    ->options(['BRN' => 'BRN', 'NRIC' => 'NRIC', 'PASSPORT' => 'Passport', 'ARMY' => 'Army ID'])
                    ->native(true)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('einvoice_id_value')->label('ID value')->maxLength(50)->columnSpan(2),
                Forms\Components\Textarea::make('einvoice_address')->label('E-invoice address')->rows(2)->columnSpan(12),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function creditFields(): array
    {
        return [
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\Select::make('credit_term_days')
                    ->label('Credit term')
                    ->options(static::creditTermOptions())
                    ->default(0)
                    ->native(true)
                    ->required(),
                Forms\Components\TextInput::make('credit_limit')
                    ->label('Credit limit')
                    ->numeric()
                    ->prefix('RM')
                    ->default(0),
                Forms\Components\Select::make('aging_on')
                    ->label('Aging on')
                    ->options([
                        'invoice_date' => 'Invoice date (I)',
                        'due_date' => 'Due date (D)',
                    ])
                    ->default('due_date')
                    ->native(true)
                    ->required(),
                Forms\Components\Select::make('statement_type')
                    ->label('Statement type')
                    ->options([
                        'open_item' => 'Open item (O)',
                        'balance_forward' => 'Balance forward (B)',
                        'no_statement' => 'No statement (N)',
                    ])
                    ->default('balance_forward')
                    ->native(true)
                    ->required(),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    public static function noteFields(): array
    {
        return [
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\DatePicker::make('account_opening_date')
                    ->label('Account opening date')
                    ->columnSpan(2),
                Forms\Components\Textarea::make('mailing_address')
                    ->label('Mailing address')
                    ->rows(2)
                    ->columnSpan(4),
                Forms\Components\Textarea::make('mailing_statement_remark')
                    ->label('Mailing statement remarks')
                    ->rows(2)
                    ->columnSpan(3),
                Forms\Components\Textarea::make('own_note')
                    ->label('Internal office note')
                    ->rows(2)
                    ->columnSpan(3),
            ]),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function consentFields(): array
    {
        return [
            Forms\Components\Grid::make(4)->schema([
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
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\DatePicker::make('consent_valid_from')->label('Consent valid from'),
                Forms\Components\DatePicker::make('consent_valid_until')->label('Consent valid until'),
                Forms\Components\FileUpload::make('consent_document_path')
                    ->label('Consent letter document')
                    ->disk('public')
                    ->directory('consent-letters')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->downloadable()
                    ->openable()
                    ->columnSpan(2),
            ])->visible(fn (Get $get): bool => $get('consent_letter_type') !== \App\Domains\MasterData\Models\Customer::CONSENT_NONE && filled($get('consent_letter_type'))),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function listFields(): array
    {
        return [
            // the contact persons are the PIC list of the Contact section (user, 10 Nov 2026)
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
