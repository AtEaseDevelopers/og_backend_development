<?php

namespace App\Filament\Resources\QuotationResource\Schemas;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Support\CurrentCompany;
use App\Support\QuotationHistoryPanel;
use App\Support\QuotationMatrix;
use App\Support\QuotationPricingLookup;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;

class QuotationForm
{
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
            Forms\Components\Hidden::make('branch_id')
                ->default(fn () => CurrentCompany::branchId())
                ->required(),
            Forms\Components\Hidden::make('pricing_source')
                ->default('default'),
            Forms\Components\Hidden::make('status')
                ->default(QuotationStatus::Draft->value),
            Forms\Components\Hidden::make('tax_amount')
                ->default(0),

            static::basicDetailsSection(),
            static::quotationHistorySection(),
            static::quotationDetailsSection(),
            static::documentPreviewSection(),
        ];
    }

    protected static function basicDetailsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Quotation Basic Details')
            ->collapsible()
            ->compact()
            ->schema([
                Forms\Components\Fieldset::make('Quotation info')
                    ->schema([
                        Forms\Components\Grid::make(12)->schema([
                            Forms\Components\DatePicker::make('quoted_at')
                                ->label('Quotation date')
                                ->default(now())
                                ->required()
                                ->columnSpan(['default' => 12, 'md' => 3, 'xl' => 2]),
                            Forms\Components\TextInput::make('number')
                                ->label('Ref No.')
                                ->disabled()
                                ->dehydrated()
                                ->placeholder('Auto')
                                ->columnSpan(['default' => 12, 'md' => 3, 'xl' => 2]),
                            Forms\Components\DatePicker::make('valid_until')
                                ->label('Valid until')
                                ->columnSpan(['default' => 12, 'md' => 3, 'xl' => 2]),
                            Forms\Components\DatePicker::make('expected_delivery_date')
                                ->label('Expected delivery')
                                ->columnSpan(['default' => 12, 'md' => 3, 'xl' => 2]),
                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(['default' => 12, 'md' => 3, 'xl' => 2]),
                            Forms\Components\Select::make('salesperson_id')
                                ->relationship('salesperson', 'name')
                                ->searchable()
                                ->label('Salesperson')
                                ->columnSpan(['default' => 12, 'md' => 6, 'xl' => 2]),
                        ]),
                        Forms\Components\Grid::make(12)->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Title')
                                ->default('Quotation Of Transport Charges')
                                ->columnSpan(['default' => 12, 'lg' => 6]),
                            Forms\Components\TextInput::make('terms_of_payment')
                                ->label('Terms')
                                ->columnSpan(['default' => 12, 'md' => 6, 'lg' => 3]),
                            Forms\Components\TextInput::make('issued_by_name')
                                ->label('Issued by')
                                ->default(fn () => auth()->user()?->name)
                                ->columnSpan(['default' => 12, 'md' => 6, 'lg' => 3]),
                        ]),
                        Forms\Components\Grid::make(12)->schema([
                            Forms\Components\TextInput::make('attention')
                                ->label('Attn')
                                ->columnSpan(['default' => 12, 'md' => 4]),
                            Forms\Components\TextInput::make('customer_phone_alt')
                                ->label('Tel (alt)')
                                ->columnSpan(['default' => 12, 'md' => 4]),
                            Forms\Components\TextInput::make('customer_fax')
                                ->label('Fax')
                                ->columnSpan(['default' => 12, 'md' => 4]),
                        ]),
                    ]),
                Forms\Components\Fieldset::make('Consignor & consignee')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Forms\Components\Group::make(static::consignorFields())->columnSpan(1),
                        Forms\Components\Group::make(static::consigneeFields())->columnSpan(1),
                    ]),
            ]);
    }

    /** @return list<Forms\Components\Component> */
    private static function consignorFields(): array
    {
        return [
            Forms\Components\Select::make('customer_id')
                ->label('Consignor')
                ->relationship('customer', 'company_name')
                ->getOptionLabelFromRecordUsing(fn (Customer $record) => trim(($record->code ? $record->code.' — ' : '').$record->company_name))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->placeholder('Please input the company name')
                ->afterStateUpdated(fn (?string $state, Set $set) => static::fillConsignorFields($state, $set)),
            Forms\Components\Select::make('from_location_id')
                ->label('FROM')
                ->options(fn () => static::locationOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    if ($state) {
                        return;
                    }

                    if ($get('needs_additional_task')) {
                        $set('additional_task_to_location_id', null);
                    }
                }),
            Forms\Components\TextInput::make('consignor_brn')
                ->label('Company Number')
                ->placeholder('Input to update the company number'),
            Forms\Components\Textarea::make('customer_address')
                ->label('Billing Address')
                ->rows(4),
            Forms\Components\Select::make('pickup_location_preset')
                ->label('Pickup Location')
                ->placeholder('Please select the branch of consignor or add new')
                ->options(fn (Get $get) => static::customerAddressOptions($get('customer_id')))
                ->searchable()
                ->dehydrated(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set) => static::fillPickupLocation($state, $set)),
            Forms\Components\Textarea::make('pickup_location')
                ->label('Pickup location detail')
                ->rows(2),
        ];
    }

    /** @return list<Forms\Components\Component> */
    private static function consigneeFields(): array
    {
        return [
            Forms\Components\TextInput::make('consignee_name')
                ->label('Consignee')
                ->placeholder('Please input the company name')
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    if ($state) {
                        static::syncConsigneeDestination($state, $set, $get);

                        return;
                    }

                    $set('history_destination', null);
                }),
            Forms\Components\Select::make('to_location_id')
                ->label('TO')
                ->options(fn () => static::locationOptions())
                ->searchable()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    if ($state) {
                        static::syncToDestination($state, $set, $get);

                        return;
                    }

                    $set('history_destination', null);
                }),
            Forms\Components\TextInput::make('consignee_brn')
                ->label('Company Number')
                ->placeholder('Input to update the company number'),
            Forms\Components\Textarea::make('consignee_address')
                ->label('Billing Address')
                ->rows(4),
            Forms\Components\Select::make('drop_off_location_preset')
                ->label('Drop Off Location')
                ->placeholder('Please select the branch of consignee or add new')
                ->options(fn (Get $get) => static::customerAddressOptions($get('customer_id')))
                ->searchable()
                ->dehydrated(false)
                ->live()
                ->afterStateUpdated(fn (?string $state, Set $set) => static::fillDropOffLocation($state, $set)),
            Forms\Components\Textarea::make('drop_off_location')
                ->label('Drop off location detail')
                ->rows(2),
        ];
    }

    protected static function quotationHistorySection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Quotation History')
            ->collapsible()
            ->collapsed(false)
            ->compact()
            ->schema([
                Forms\Components\Grid::make(12)->schema([
                    Forms\Components\TextInput::make('history_search')
                        ->label('Search')
                        ->placeholder('Search pricing history…')
                        ->prefixIcon('heroicon-m-magnifying-glass')
                        ->live(debounce: 400)
                        ->dehydrated(false)
                        ->columnSpan(5),
                    Forms\Components\Select::make('history_measurement')
                        ->label('Measurement')
                        ->placeholder('All')
                        ->options(fn (Get $get) => collect(
                            app(QuotationHistoryPanel::class)->measurementOptions(
                                $get('customer_id') ? (int) $get('customer_id') : null,
                            )
                        )->mapWithKeys(fn (string $name) => [$name => $name]))
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->columnSpan(4),
                    Forms\Components\Select::make('history_destination')
                        ->label('Consignee route')
                        ->options(fn (Get $get) => collect($get('matrix_columns') ?? [])
                            ->filter()
                            ->mapWithKeys(fn (string $destination) => [$destination => $destination])
                            ->all())
                        ->default(fn (Get $get) => $get('consignee_name') ?: collect($get('matrix_columns') ?? [])->first())
                        ->live()
                        ->dehydrated(false)
                        ->columnSpan(3),
                ]),
                Forms\Components\Grid::make(1)->schema([
                    Forms\Components\ViewField::make('history_previous_panel')
                        ->hiddenLabel()
                        ->view('filament.forms.quotation-history-other')
                        ->viewData(fn (Get $get) => static::previousHistoryData($get)),
                    Forms\Components\ViewField::make('history_special_panel')
                        ->hiddenLabel()
                        ->view('filament.forms.quotation-history-special')
                        ->viewData(fn (Get $get) => static::specialHistoryData($get)),
                    Forms\Components\ViewField::make('history_master_panel')
                        ->hiddenLabel()
                        ->view('filament.forms.quotation-history-master')
                        ->viewData(fn (Get $get) => static::masterHistoryData($get)),
                ]),
            ])
            ->visible(fn (Get $get) => filled($get('customer_id')));
    }

    protected static function quotationDetailsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Quotation Details')
            ->collapsible()
            ->collapsed(false)
            ->compact()
            ->schema([
                Forms\Components\TagsInput::make('matrix_columns')
                    ->label('Destinations')
                    ->placeholder('Add destination')
                    ->default(['Seremban', 'Melaka', 'Johor'])
                    ->live()
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('matrix_rows')
                    ->label('Transport charges')
                    ->helperText('Select lorry, item, or UOM — UOM rows use qty-based range tiers from master pricing (e.g. 1, 2–9, 10–19, 20+).')
                    ->schema(static::matrixRowSchema())
                    ->defaultItems(1)
                    ->reorderable()
                    ->addActionLabel('Add row')
                    ->columnSpanFull()
                    ->live(),
                Forms\Components\Placeholder::make('matrix_total_display')
                    ->label('Quotation amount')
                    ->content(fn (Get $get): string => 'RM '.number_format(static::matrixTotal($get('matrix_rows') ?? []), 2))
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Footnotes')
                    ->placeholder("*Minimum charge per point / DO RM 20\n*Minimum charges per pick up RM 60")
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    protected static function documentPreviewSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Document preview')
            ->collapsible()
            ->collapsed()
            ->compact()
            ->schema([
                Forms\Components\ViewField::make('quotation_preview')
                    ->hiddenLabel()
                    ->view('filament.forms.quotation-body-preview')
                    ->viewData(fn (Get $get) => [
                        'title' => $get('title') ?: 'Quotation Of Transport Charges',
                        'terms' => $get('terms_of_payment') ?: '30 days',
                        'rateMatrix' => app(QuotationMatrix::class)->preview(
                            $get('matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'],
                            $get('matrix_rows') ?? [],
                        ),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string, mixed> */
    private static function masterHistoryData(Get $get): array
    {
        $destinations = $get('matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'];

        return [
            'destinations' => $destinations,
            'rows' => app(QuotationHistoryPanel::class)->defaultPricesForAllMeasurements($destinations),
        ];
    }

    /** @return array<string, mixed> */
    private static function previousHistoryData(Get $get): array
    {
        $customerId = $get('customer_id') ? (int) $get('customer_id') : null;

        return [
            'rows' => app(\App\Support\CustomerQuotationPriceHistory::class)->previousQuotationPrices(
                $customerId,
                $get('history_search'),
                $get('history_measurement'),
                $get('history_destination') ?: $get('consignee_name'),
                Filament::getTenant()?->code,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function specialHistoryData(Get $get): array
    {
        $customerId = $get('customer_id') ? (int) $get('customer_id') : null;

        return [
            'rows' => app(QuotationHistoryPanel::class)->specialPrices(
                $customerId,
                $get('history_destination') ?: $get('consignee_name'),
            ),
        ];
    }

    private static function consignorLabel(Get $get): string
    {
        if ($customerId = $get('customer_id')) {
            return Customer::query()->find($customerId)?->company_name ?? 'Consignor';
        }

        return 'Consignor';
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

    public static function fillConsignorFields(?string $customerId, Set $set): void
    {
        if (! $customerId) {
            static::clearConsignorFields($set);

            return;
        }

        $customer = Customer::query()->with(['pics', 'addresses'])->find($customerId);

        if (! $customer) {
            return;
        }

        $set('customer_address', $customer->address ?? '');
        $set('consignor_brn', $customer->brn ?? '');
        $set('attention', $customer->pics->firstWhere('is_default', true)?->name
            ?? $customer->pics->first()?->name
            ?? '');
        $set('terms_of_payment', $customer->credit_term_days
            ? $customer->credit_term_days.' days'
            : 'Cash / COD');

        static::defaultFromLocation($set);

        $defaultAddress = $customer->addresses->firstWhere('is_default', true)
            ?? $customer->addresses->first();

        if ($defaultAddress) {
            $set('pickup_location_preset', (string) $defaultAddress->id);
            static::fillPickupLocation((string) $defaultAddress->id, $set);
        }
    }

    public static function fillPickupLocation(?string $addressId, Set $set): void
    {
        if (! $addressId) {
            $set('pickup_location', null);

            return;
        }

        $address = CustomerAddress::query()->find($addressId);

        if ($address) {
            $set('pickup_location', trim(collect([
                $address->label,
                $address->address,
                $address->postcode,
                $address->city,
                $address->state,
            ])->filter()->implode(', ')));
        }
    }

    public static function fillDropOffLocation(?string $addressId, Set $set): void
    {
        if (! $addressId) {
            static::clearDropOffLocationFields($set);

            return;
        }

        $address = CustomerAddress::query()->find($addressId);

        if ($address) {
            if ($address->label) {
                $set('consignee_name', $address->label);
            }
            $set('consignee_address', $address->address);
            $set('drop_off_location', trim(collect([
                $address->label,
                $address->address,
                $address->postcode,
                $address->city,
                $address->state,
            ])->filter()->implode(', ')));
        }
    }

    private static function clearConsignorFields(Set $set): void
    {
        $set('customer_address', null);
        $set('consignor_brn', null);
        $set('attention', null);
        $set('terms_of_payment', null);
        $set('from_location_id', null);
        $set('pickup_location_preset', null);
        $set('pickup_location', null);
    }

    private static function clearDropOffLocationFields(Set $set): void
    {
        $set('consignee_name', null);
        $set('consignee_address', null);
        $set('drop_off_location', null);
    }

    public static function syncConsigneeDestination(?string $consigneeName, Set $set, Get $get): void
    {
        if (! $consigneeName) {
            return;
        }

        $set('history_destination', $consigneeName);

        $columns = collect($get('matrix_columns') ?? [])->filter()->values();

        if (! $columns->contains($consigneeName)) {
            $set('matrix_columns', $columns->push($consigneeName)->all());
        }
    }

    public static function syncToDestination(?string $locationId, Set $set, Get $get): void
    {
        if (! $locationId) {
            return;
        }

        $location = Location::query()->find($locationId);

        if (! $location) {
            return;
        }

        $set('history_destination', $location->name);

        $columns = collect($get('matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'])->filter()->values();

        if (! $columns->contains($location->name)) {
            $set('matrix_columns', $columns->push($location->name)->unique()->values()->all());
        }
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

    /** @return list<Forms\Components\Component> */
    private static function matrixRowSchema(): array
    {
        return [
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\Select::make('line_type')
                    ->label('Type')
                    ->options([
                        'lorry' => 'Lorry type',
                        'item' => 'Transport item',
                        'uom' => 'UOM',
                    ])
                    ->default('lorry')
                    ->live()
                    ->columnSpan(['default' => 12, 'md' => 2])
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('catalog_key', null);
                        $set('item_name', null);
                        $set('quantity', 1);
                        $set('prices', []);
                    }),
                Forms\Components\Select::make('catalog_key')
                    ->label(fn (Get $get): string => match ($get('line_type')) {
                        'lorry' => 'Lorry type',
                        'uom' => 'UOM',
                        default => 'Transport item',
                    })
                    ->options(fn (Get $get) => app(QuotationPricingLookup::class)
                        ->catalogOptionsForType($get('line_type') ?: 'item'))
                    ->searchable()
                    ->columnSpan(['default' => 12, 'md' => fn (Get $get): int => ($get('line_type') ?? '') === 'uom' ? 3 : 4])
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        static::applyCatalogSelection($state, $set, $get);
                    }),
                Forms\Components\TextInput::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->minValue(0.01)
                    ->default(1)
                    ->live(debounce: 400)
                    ->visible(fn (Get $get): bool => ($get('line_type') ?? '') === 'uom')
                    ->helperText('Unit rate changes by qty tier (e.g. 1–9 vs 10+).')
                    ->columnSpan(['default' => 12, 'md' => 2])
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        static::refreshUomRowPrices($set, $get, max(0.01, (float) ($state ?? 1)));
                    }),
                Forms\Components\TextInput::make('item_name')
                    ->label('Description')
                    ->required()
                    ->columnSpan(['default' => 12, 'md' => fn (Get $get): int => ($get('line_type') ?? '') === 'uom' ? 3 : 4]),
                Forms\Components\Placeholder::make('row_amount_display')
                    ->label('Line total')
                    ->content(fn (Get $get): string => static::formatRowAmountSummary(
                        $get('prices') ?? [],
                        ($get('line_type') ?? '') === 'uom' ? (float) ($get('quantity') ?? 1) : 1.0,
                        ($get('line_type') ?? '') === 'uom',
                    ))
                    ->columnSpan(['default' => 12, 'md' => 2]),
            ]),
            Forms\Components\ViewField::make('uom_tier_hint')
                ->hiddenLabel()
                ->view('filament.forms.quotation-uom-tier-hint')
                ->viewData(fn (Get $get): array => [
                    'summary' => static::uomTierHintData($get),
                ])
                ->visible(fn (Get $get): bool => ($get('line_type') ?? '') === 'uom' && filled($get('catalog_key')))
                ->columnSpanFull(),
            Forms\Components\Grid::make(3)
                ->schema(fn (Get $get): array => static::priceFieldsForColumns(
                    $get('../../matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'],
                    ($get('line_type') ?? 'item') === 'lorry',
                    ($get('line_type') ?? 'item') === 'uom',
                )),
        ];
    }

    private static function applyCatalogSelection(?string $state, Set $set, Get $get): void
    {
        if (! $state) {
            $set('item_name', null);
            $set('quantity', 1);
            $set('prices', []);
            $set('../../pricing_source', 'default');

            return;
        }

        $lookup = app(QuotationPricingLookup::class);
        $name = $lookup->resolveCatalogName($state);

        if (! $name) {
            return;
        }

        $set('item_name', $name);

        if (($get('line_type') ?? '') !== 'uom') {
            $set('quantity', 1);
        }

        static::refreshRowPrices($set, $get, $name);
    }

    private static function refreshUomRowPrices(Set $set, Get $get, ?float $quantityOverride = null): void
    {
        if (($get('line_type') ?? '') !== 'uom') {
            return;
        }

        $name = $get('item_name');

        if (! $name) {
            return;
        }

        static::refreshRowPrices($set, $get, $name, $quantityOverride);
    }

    private static function refreshRowPrices(Set $set, Get $get, string $name, ?float $quantityOverride = null): void
    {
        $lookup = app(QuotationPricingLookup::class);
        $columns = $get('../../matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'];
        $customerId = $get('../../customer_id') ? (int) $get('../../customer_id') : null;
        $quantity = ($get('line_type') ?? '') === 'uom'
            ? ($quantityOverride ?? max(0.01, (float) ($get('quantity') ?? 1)))
            : 1.0;
        $prices = [];
        $pricingSource = 'default';

        foreach ($columns as $column) {
            $resolved = $lookup->lookupForCustomer($customerId, $name, $column, $quantity);
            $prices[$column] = $resolved['price'];

            if ($resolved['source'] === 'special') {
                $pricingSource = 'special';
            } elseif ($resolved['source'] === 'previous' && $pricingSource !== 'special') {
                $pricingSource = 'previous';
            }
        }

        $set('prices', $prices);
        $set('../../pricing_source', collect($prices)->filter(fn ($price) => $price !== null)->isNotEmpty()
            ? $pricingSource
            : 'manual');
    }

    /** @return array{quantity: float, destinations: list<array<string, mixed>>} */
    private static function uomTierHintData(Get $get): array
    {
        $name = $get('item_name');

        if (! $name) {
            return ['quantity' => 1, 'destinations' => []];
        }

        $columns = $get('../../matrix_columns') ?? ['Seremban', 'Melaka', 'Johor'];
        $quantity = max(0.01, (float) ($get('quantity') ?? 1));

        return app(QuotationPricingLookup::class)->uomTierSummary($name, $columns, $quantity);
    }

    /** @param  array<string, mixed>  $prices */
    private static function formatRowAmountSummary(array $prices, float $quantity = 1, bool $isUom = false): string
    {
        $filled = collect($prices)->filter(fn ($price) => $price !== null && $price !== '');

        if ($filled->isEmpty()) {
            return '—';
        }

        $totals = $filled->map(fn ($price) => round((float) $price * ($isUom ? $quantity : 1), 2));

        if ($totals->count() === 1) {
            $total = $totals->first();
            $unit = (float) $filled->first();

            if ($isUom && $quantity !== 1.0) {
                return 'RM '.number_format((float) $total, 2).' ('.number_format($quantity, 0).' × RM '.number_format($unit, 2).')';
            }

            return 'RM '.number_format((float) $total, 2);
        }

        return $filled
            ->map(function ($price, $column) use ($quantity, $isUom) {
                $total = round((float) $price * ($isUom ? $quantity : 1), 2);

                return $column.': RM '.number_format($total, 2);
            })
            ->implode(' · ');
    }

    /** @param  list<array<string, mixed>>  $rows */
    private static function matrixTotal(array $rows): float
    {
        return round(collect($rows)->sum(function (array $row): float {
            $isUom = ($row['line_type'] ?? '') === 'uom';
            $quantity = $isUom ? max(0.01, (float) ($row['quantity'] ?? 1)) : 1.0;

            return collect($row['prices'] ?? [])
                ->filter(fn ($price) => $price !== null && $price !== '')
                ->sum(fn ($price) => round((float) $price * $quantity, 2));
        }), 2);
    }

    /** @param  list<string>  $columns
     * @return list<Forms\Components\Component>
     */
    private static function priceFieldsForColumns(array $columns, bool $readOnlyAmounts = false, bool $isUom = false): array
    {
        $columns = collect($columns)->filter()->values()->all();

        if ($columns === []) {
            $columns = ['Seremban', 'Melaka', 'Johor'];
        }

        return collect($columns)->map(
            function (string $column) use ($readOnlyAmounts, $isUom) {
                $field = Forms\Components\TextInput::make('prices.'.$column)
                    ->label($isUom ? "{$column} (unit)" : $column)
                    ->numeric()
                    ->prefix('RM');

                if ($readOnlyAmounts || $isUom) {
                    $field->disabled()->dehydrated();
                }

                return $field;
            },
        )->all();
    }
}
