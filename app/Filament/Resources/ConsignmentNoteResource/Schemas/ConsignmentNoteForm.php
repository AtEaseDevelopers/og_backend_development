<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Schemas;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\DocumentNumberSequence;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;

class ConsignmentNoteForm
{
    /** @return array<string, string> */
    public static function unitMeasureOptions(): array
    {
        return [
            'BOX' => '1 — BOX',
            'PC' => '2 — PC',
            'ROLL' => '3 — ROLL',
            'LOAD' => '4 — LOAD',
            'PLT' => '5 — PLT',
            'BLD' => '6 — BLD',
            'CASE' => '7 — CASE',
            'TON' => '8 — TON',
        ];
    }

    public static function configure(Form $form): Form
    {
        return $form
            ->schema(static::schema())
            ->columns(1);
    }

    /** @return list<Forms\Components\Component> */
    public static function schema(): array
    {
        return [
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => CurrentCompany::id())
                ->required(),
            Forms\Components\Hidden::make('source_branch_id')
                ->default(fn () => CurrentCompany::branchId())
                ->required(),
            Forms\Components\Hidden::make('customer_name'),
            Forms\Components\Hidden::make('customer_brn'),
            Forms\Components\Hidden::make('customer_tin'),
            Forms\Components\Hidden::make('billing_type')
                ->default(CsnBillingType::CashBill->value),
            Forms\Components\Hidden::make('status')
                ->default(CsnStatus::Confirmed->value),
            Forms\Components\Hidden::make('subtotal')
                ->default(0)
                ->dehydrated(),
            Forms\Components\Hidden::make('tax_amount')
                ->default(0)
                ->dehydrated(),
            Forms\Components\Hidden::make('total_amount')
                ->default(0)
                ->dehydrated(),

            Forms\Components\Section::make('Period & quotation')
                ->collapsible()
                ->compact()
                ->schema([
                    Forms\Components\Grid::make(12)->schema([
                        Forms\Components\Placeholder::make('period_year')
                            ->label('Year')
                            ->content(fn (Get $get) => static::periodYear($get('issued_at'))),
                        Forms\Components\Placeholder::make('period_month')
                            ->label('Month (1–12)')
                            ->content(fn (Get $get) => static::periodMonth($get('issued_at'))),
                        Forms\Components\Placeholder::make('last_csn_no')
                            ->label('Last CSN No.')
                            ->content(fn () => static::lastCsnDisplay()),
                        Forms\Components\Select::make('quotation_id')
                            ->label('Quotation')
                            ->options(fn (Get $get) => static::quotationOptions($get('customer_id')))
                            ->searchable()
                            ->live()
                            ->columnSpan(3)
                            ->afterStateUpdated(fn (?string $state, Set $set) => static::fillFromQuotation($state, $set)),
                        Forms\Components\Select::make('quotation_destination_id')
                            ->label('Destination')
                            ->options(fn (Get $get) => static::destinationOptions($get('quotation_id')))
                            ->searchable()
                            ->live()
                            ->columnSpan(3)
                            ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => static::fillFromDestination(
                                $get('quotation_id'),
                                $state,
                                $set,
                            )),
                    ]),
                ]),

            Forms\Components\Grid::make(12)->schema([
                static::leftColumn()->columnSpan(5),
                static::middleColumn()->columnSpan(3),
                static::rightColumn()->columnSpan(4),
            ]),

            Forms\Components\Section::make('Assign lorry / driver')
                ->collapsible()
                ->collapsed()
                ->description('Optional on create — you can also assign later from the CSN list.')
                ->compact()
                ->schema([
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\Select::make('assign_lorry_id')
                            ->label('Main lorry')
                            ->options(fn () => ConsignmentNoteResource::lorryOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $lorry = Lorry::query()->find($state);
                                $set('assign_driver_id', $lorry?->default_driver_id);
                            })
                            ->dehydrated(false),
                        Forms\Components\Select::make('assign_driver_id')
                            ->label('Driver')
                            ->options(fn () => ConsignmentNoteResource::driverOptions())
                            ->searchable()
                            ->dehydrated(false),
                        Forms\Components\Select::make('assign_sub_lorry_ids')
                            ->label('Additional lorries')
                            ->options(fn (Get $get) => ConsignmentNoteResource::lorryOptions(
                                excludeIds: array_filter([(int) $get('assign_lorry_id')])
                            ))
                            ->multiple()
                            ->searchable()
                            ->dehydrated(false)
                            ->columnSpan(2),
                        Forms\Components\DatePicker::make('assign_operating_date')
                            ->label('Operating date')
                            ->default(now())
                            ->dehydrated(false),
                    ]),
                ])
                ->visibleOn('create'),

            Forms\Components\Section::make('Document preview')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\ViewField::make('csn_preview')
                        ->view('filament.forms.consignment-note-preview')
                        ->viewData(fn (Get $get) => static::previewData($get))
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected static function leftColumn(): Forms\Components\Section
    {
        return Forms\Components\Section::make('CSN details')
            ->collapsible()
            ->compact()
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->label('CSN No.')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Auto'),
                Forms\Components\DatePicker::make('issued_at')
                    ->label('CSN Date')
                    ->default(now())
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('do_number')
                    ->label('D/O No.'),
                Forms\Components\TextInput::make('customer_reference')
                    ->label('Reference No.'),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('job_no')
                        ->label('Job No.'),
                    Forms\Components\DatePicker::make('job_date')
                        ->label('Job date'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('from_location_id')
                        ->label('From area')
                        ->options(fn () => static::locationOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => static::syncLocationLabel($state, $set, 'from_location_label')),
                    Forms\Components\Placeholder::make('from_location_label')
                        ->label('')
                        ->content(fn (Get $get) => static::locationLabel($get('from_location_id')) ?: '—'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('to_location_id')
                        ->label('To area')
                        ->options(fn () => static::locationOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => static::syncLocationLabel($state, $set, 'to_location_label')),
                    Forms\Components\Placeholder::make('to_location_label')
                        ->label('')
                        ->content(fn (Get $get) => static::locationLabel($get('to_location_id')) ?: '—'),
                ]),
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(fn () => ConsignmentNoteResource::customerOptions())
                    ->getOptionLabelUsing(fn ($value) => Customer::find($value)?->company_name ?? $value)
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => static::fillFromCustomer($state, $set)),
                Forms\Components\Placeholder::make('customer_display_name')
                    ->label('Customer name')
                    ->content(fn (Get $get) => $get('customer_name') ?: '—'),
                Forms\Components\Placeholder::make('customer_address_display')
                    ->label('Customer address')
                    ->content(function (Get $get) {
                        if ($customerId = $get('customer_id')) {
                            return Customer::query()->find($customerId)?->address ?: '—';
                        }

                        return '—';
                    }),
                Forms\Components\Placeholder::make('customer_phone_display')
                    ->label('Telephone No.')
                    ->content(fn (Get $get) => $get('customer_phone') ?: '—'),
                Forms\Components\Hidden::make('customer_phone'),
                Forms\Components\Repeater::make('lines')
                    ->relationship()
                    ->label('')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->default(0)
                            ->live(onBlur: true),
                        Forms\Components\Select::make('uom')
                            ->label('Unit measure')
                            ->options(static::unitMeasureOptions())
                            ->searchable(),
                        Forms\Components\TextInput::make('item_name')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->maxItems(1)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('remarks')
                    ->label('Remark')
                    ->rows(2),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('profit_sharing_period')
                        ->label('PS period')
                        ->placeholder('YYYY/MM')
                        ->default(fn () => now()->format('Y/m')),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('ps_job_no')
                            ->label('PS job no.'),
                        Forms\Components\DatePicker::make('ps_job_date')
                            ->label('PS job date'),
                    ]),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('gl_account')
                        ->label('Account'),
                    Forms\Components\Placeholder::make('gl_account_name_display')
                        ->label('Account name')
                        ->content(fn (Get $get) => $get('gl_account_name') ?: '—'),
                ]),
                Forms\Components\Hidden::make('gl_account_name'),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('tax_code')
                        ->label('Tax code')
                        ->default('SST'),
                    Forms\Components\Placeholder::make('tax_code_name_display')
                        ->label('Tax description')
                        ->content(fn (Get $get) => $get('tax_code_name') ?: 'Sales & Services Tax'),
                ]),
                Forms\Components\Hidden::make('tax_code_name')
                    ->default('Sales & Services Tax'),
            ]);
    }

    protected static function middleColumn(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Consignor & consignee')
            ->collapsible()
            ->compact()
            ->schema([
                Forms\Components\TextInput::make('consignor_name')
                    ->label('Consignor'),
                Forms\Components\Textarea::make('consignor_address')
                    ->label('Consignor address')
                    ->rows(4),
                Forms\Components\TextInput::make('consignor_phone')
                    ->label('Consignor telephone')
                    ->tel(),
                Forms\Components\Select::make('delivery_address_preset')
                    ->label('Load consignee from address')
                    ->options(fn (Get $get) => static::customerAddressOptions($get('customer_id')))
                    ->searchable()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => static::fillFromCustomerAddress($state, $set)),
                Forms\Components\TextInput::make('consignee_name')
                    ->label('Consignee')
                    ->required(),
                Forms\Components\Textarea::make('delivery_address')
                    ->label('Consignee address')
                    ->required()
                    ->rows(4),
                Forms\Components\TextInput::make('consignee_phone')
                    ->label('Consignee telephone')
                    ->tel(),
                Forms\Components\TextInput::make('consignee_pic')
                    ->label('Attention / PIC'),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('delivery_postcode')
                        ->label('Postcode'),
                    Forms\Components\TextInput::make('delivery_city')
                        ->label('City'),
                    Forms\Components\TextInput::make('delivery_state')
                        ->label('State'),
                ]),
            ]);
    }

    protected static function rightColumn(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Charges & billing')
            ->collapsible()
            ->compact()
            ->schema([
                Forms\Components\TagsInput::make('other_do_numbers')
                    ->label('Other D/O No.')
                    ->placeholder('Add D/O number')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('marking')
                    ->label('Marking')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('transport_charges')
                    ->label('Transport charges')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\TextInput::make('master_charges')
                    ->label('Master charges')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\TextInput::make('profit_sharing_amount')
                    ->label('Profit sharing')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\TextInput::make('expenses')
                    ->label('Expenses')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\Placeholder::make('subtotal_display')
                    ->label('Sub total')
                    ->content(fn (Get $get) => 'RM '.number_format(static::chargeSubtotal($get), 2)),
                Forms\Components\TextInput::make('discount')
                    ->label('Discount')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\Placeholder::make('ac_amount_display')
                    ->label('A/C amount')
                    ->content(fn (Get $get) => 'RM '.number_format(static::accountAmount($get), 2)),
                Forms\Components\TextInput::make('tax_rate')
                    ->label('Tax rate (%)')
                    ->numeric()
                    ->default(6)
                    ->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\TextInput::make('cost_center')
                    ->label('Cost center'),
                Forms\Components\ToggleButtons::make('is_taxable')
                    ->label('Tax')
                    ->boolean()
                    ->inline()
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::syncChargeTotals($set, $get)),
                Forms\Components\ToggleButtons::make('advance_taken')
                    ->label('Advance taken')
                    ->boolean()
                    ->inline()
                    ->default(false),
                Forms\Components\ToggleButtons::make('issue_invoice')
                    ->label('Issue invoice')
                    ->boolean()
                    ->inline()
                    ->default(true),
            ]);
    }

    public static function periodYear(mixed $issuedAt): string
    {
        return $issuedAt
            ? Carbon::parse($issuedAt)->format('Y')
            : now()->format('Y');
    }

    public static function periodMonth(mixed $issuedAt): string
    {
        return $issuedAt
            ? Carbon::parse($issuedAt)->format('n')
            : now()->format('n');
    }

    public static function lastCsnDisplay(): string
    {
        $branchId = CurrentCompany::branchId();

        if (! $branchId) {
            return '—';
        }

        $branch = Branch::query()->find($branchId);
        $period = now()->format('Ym');

        $last = DocumentNumberSequence::query()
            ->where('branch_id', $branchId)
            ->where('document_type', DocumentType::Csn->value)
            ->where('period', $period)
            ->value('last_number') ?? 0;

        return trim(($branch?->code ?? '').' '.$last);
    }

    /** @return array<int, string> */
    public static function locationOptions(): array
    {
        return Location::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Location $location) => [
                $location->id => trim($location->code.' — '.$location->name),
            ])
            ->all();
    }

    public static function locationLabel(?string $locationId): ?string
    {
        if (! $locationId) {
            return null;
        }

        $location = Location::query()->find($locationId);

        return $location ? strtoupper($location->name) : null;
    }

    public static function syncLocationLabel(?string $locationId, Set $set, string $field): void
    {
        $set($field, static::locationLabel($locationId));
    }

    /** @param  array<string, mixed>  $values */
    public static function calculateTotals(array $values): array
    {
        $subtotal = round(
            (float) ($values['transport_charges'] ?? 0)
            + (float) ($values['master_charges'] ?? 0)
            + (float) ($values['profit_sharing_amount'] ?? 0)
            + (float) ($values['expenses'] ?? 0),
            2
        );
        $discount = (float) ($values['discount'] ?? 0);
        $taxable = filter_var($values['is_taxable'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $taxRate = (float) ($values['tax_rate'] ?? 0);
        $taxBase = max($subtotal - $discount, 0);
        $tax = $taxable ? round($taxBase * $taxRate / 100, 2) : 0;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => round($taxBase + $tax, 2),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function applyPersistedTotals(array $data): array
    {
        return array_merge($data, static::calculateTotals($data));
    }

    public static function chargeSubtotal(Get $get): float
    {
        return static::calculateTotals([
            'transport_charges' => $get('transport_charges'),
            'master_charges' => $get('master_charges'),
            'profit_sharing_amount' => $get('profit_sharing_amount'),
            'expenses' => $get('expenses'),
        ])['subtotal'];
    }

    public static function accountAmount(Get $get): float
    {
        return static::calculateTotals([
            'transport_charges' => $get('transport_charges'),
            'master_charges' => $get('master_charges'),
            'profit_sharing_amount' => $get('profit_sharing_amount'),
            'expenses' => $get('expenses'),
            'discount' => $get('discount'),
            'tax_rate' => $get('tax_rate'),
            'is_taxable' => $get('is_taxable'),
        ])['total_amount'];
    }

    public static function syncChargeTotals(Set $set, Get $get): void
    {
        $totals = static::calculateTotals([
            'transport_charges' => $get('transport_charges'),
            'master_charges' => $get('master_charges'),
            'profit_sharing_amount' => $get('profit_sharing_amount'),
            'expenses' => $get('expenses'),
            'discount' => $get('discount'),
            'tax_rate' => $get('tax_rate'),
            'is_taxable' => $get('is_taxable'),
        ]);

        $set('subtotal', $totals['subtotal']);
        $set('tax_amount', $totals['tax_amount']);
        $set('total_amount', $totals['total_amount']);
    }

    public static function fillFromCustomer(?string $customerId, Set $set): void
    {
        if (! $customerId) {
            return;
        }

        $customer = Customer::query()->with('addresses')->find($customerId);

        if (! $customer) {
            return;
        }

        $set('customer_name', $customer->company_name);
        $set('customer_brn', $customer->brn);
        $set('customer_tin', $customer->tin);
        $set('customer_phone', $customer->phone);
        $set('consignor_name', $customer->company_name);
        $set('consignor_address', $customer->address);
        $set('consignor_phone', $customer->phone);
        $set('gl_account', $customer->control_account);
        $set('gl_account_name', $customer->control_account ? 'INCOME — INVOICE' : null);
        $set('tax_code', $customer->tax_type ?: 'SST');
        $set('tax_code_name', 'Sales & Services Tax');

        if ($customer->is_credit) {
            $set('billing_type', CsnBillingType::Term->value);
        }

        $defaultAddress = $customer->addresses->firstWhere('is_default', true)
            ?? $customer->addresses->first();

        if ($defaultAddress) {
            static::applyCustomerAddress($defaultAddress, $set);
            $set('delivery_address_preset', (string) $defaultAddress->id);
        }

        static::defaultFromLocation($set);
    }

    protected static function defaultFromLocation(Set $set): void
    {
        $branchId = CurrentCompany::branchId();

        if (! $branchId) {
            return;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            return;
        }

        $location = Location::query()
            ->where('is_active', true)
            ->where(function ($query) use ($branch) {
                $query->where('code', $branch->code)
                    ->orWhere('name', 'like', '%'.$branch->name.'%');
            })
            ->first();

        if ($location) {
            $set('from_location_id', (string) $location->id);
        }
    }

    public static function fillFromQuotation(?string $quotationId, Set $set): void
    {
        if (! $quotationId) {
            return;
        }

        $quotation = Quotation::query()->with(['customer', 'destinations'])->find($quotationId);

        if (! $quotation) {
            return;
        }

        $set('customer_id', (string) $quotation->customer_id);
        static::fillFromCustomer((string) $quotation->customer_id, $set);

        $destination = $quotation->destinations->sortBy('sequence')->first();

        if ($destination) {
            $set('quotation_destination_id', (string) $destination->id);
            static::fillFromDestination($quotationId, (string) $destination->id, $set);
        }
    }

    public static function fillFromDestination(?string $quotationId, ?string $destinationId, Set $set): void
    {
        if (! $quotationId || ! $destinationId) {
            return;
        }

        $quotation = Quotation::query()->with(['lines'])->find($quotationId);
        $destination = QuotationDestination::query()->find($destinationId);

        if (! $quotation || ! $destination) {
            return;
        }

        $set('consignee_name', $destination->consignee_name);
        $set('consignee_pic', $destination->consignee_pic);
        $set('consignee_phone', $destination->consignee_phone);
        $set('delivery_address', $destination->address);
        $set('delivery_postcode', $destination->postcode);
        $set('delivery_state', $destination->state);
        $set('delivery_city', $destination->city);
        $set('delivery_address_preset', null);

        if ($destination->city) {
            $toLocation = Location::query()
                ->where('is_active', true)
                ->where(function ($query) use ($destination) {
                    $query->where('name', 'like', '%'.$destination->city.'%')
                        ->orWhere('code', 'like', '%'.$destination->city.'%');
                })
                ->first();

            if ($toLocation) {
                $set('to_location_id', (string) $toLocation->id);
            }
        }

        $lines = $quotation->lines
            ->where('quotation_destination_id', (int) $destinationId)
            ->values();

        $firstLine = $lines->first();

        if ($firstLine) {
            $set('lines', [[
                'item_name' => $firstLine->item_name,
                'uom' => $firstLine->uom,
                'quantity' => $firstLine->quantity,
            ]]);

            $transport = round((float) $lines->sum('line_total'), 2);
            $set('transport_charges', $transport);
            $set('subtotal', $transport);
            $set('tax_amount', 0);
            $set('total_amount', $transport);
        }
    }

    public static function fillFromCustomerAddress(?string $addressId, Set $set): void
    {
        if (! $addressId) {
            return;
        }

        $address = CustomerAddress::query()->find($addressId);

        if ($address) {
            static::applyCustomerAddress($address, $set);
        }
    }

    private static function applyCustomerAddress(CustomerAddress $address, Set $set): void
    {
        $set('consignee_name', $address->label ?: 'Consignee');
        $set('delivery_address', $address->address);
        $set('delivery_postcode', $address->postcode);
        $set('delivery_state', $address->state);
        $set('delivery_city', $address->city);
    }

    /** @return array<int, string> */
    public static function quotationOptions(?string $customerId): array
    {
        $query = Quotation::query()
            ->whereIn('status', [
                QuotationStatus::Confirmed,
                QuotationStatus::Accepted,
            ])
            ->orderByDesc('id');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('number', 'id')->all();
    }

    /** @return array<int, string> */
    public static function destinationOptions(?string $quotationId): array
    {
        if (! $quotationId) {
            return [];
        }

        return QuotationDestination::query()
            ->where('quotation_id', $quotationId)
            ->orderBy('sequence')
            ->get()
            ->mapWithKeys(fn (QuotationDestination $destination) => [
                $destination->id => trim(collect([
                    $destination->city,
                    $destination->consignee_name,
                    $destination->address,
                ])->filter()->implode(' — ')),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public static function customerAddressOptions(?string $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        return CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->get()
            ->mapWithKeys(fn (CustomerAddress $address) => [
                (string) $address->id => trim(($address->label ?: 'Address').' — '.$address->address),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public static function previewData(Get $get): array
    {
        $lines = $get('lines') ?? [];

        return [
            'number' => $get('number') ?: 'AUTO',
            'issued_at' => $get('issued_at'),
            'billing_type' => $get('billing_type'),
            'customer_name' => $get('customer_name'),
            'customer_brn' => $get('customer_brn'),
            'consignor_name' => $get('consignor_name'),
            'consignor_address' => $get('consignor_address'),
            'consignee_name' => $get('consignee_name'),
            'consignee_pic' => $get('consignee_pic'),
            'consignee_phone' => $get('consignee_phone'),
            'delivery_address' => $get('delivery_address'),
            'delivery_city' => $get('delivery_city'),
            'delivery_state' => $get('delivery_state'),
            'delivery_postcode' => $get('delivery_postcode'),
            'from_location' => static::locationLabel($get('from_location_id')),
            'to_location' => static::locationLabel($get('to_location_id')),
            'lines' => $lines,
            'transport_charges' => (float) ($get('transport_charges') ?: 0),
            'subtotal' => static::chargeSubtotal($get),
            'discount' => (float) ($get('discount') ?: 0),
            'tax_amount' => (float) ($get('tax_amount') ?: 0),
            'total_amount' => static::accountAmount($get),
            'remarks' => $get('remarks'),
            'marking' => $get('marking'),
        ];
    }
}
