<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use App\Support\UomPriceRows;
use Illuminate\Support\Carbon;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Form $form): Form
    {
        return CustomerForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('Customer')->tabs([
                Infolists\Components\Tabs\Tab::make('Account')->schema([
                    Infolists\Components\TextEntry::make('code')->label('Debtor account'),
                    Infolists\Components\TextEntry::make('company_name'),
                    Infolists\Components\TextEntry::make('brn')->label('Business Registration No (BRN)'),
                    Infolists\Components\TextEntry::make('control_account'),
                    Infolists\Components\TextEntry::make('debtor_type')->formatStateUsing(
                        fn (?string $state) => CustomerForm::debtorTypeOptions()[$state] ?? $state
                    ),
                    Infolists\Components\IconEntry::make('status')
                        ->label('Active')
                        ->boolean()
                        ->getStateUsing(fn (Customer $record) => $record->status === 'active'),
                    Infolists\Components\IconEntry::make('is_group_company')->boolean(),
                ])->columns(2),
                Infolists\Components\Tabs\Tab::make('General')->schema([
                    Infolists\Components\TextEntry::make('address')->label('Billing address')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('email'),
                    Infolists\Components\TextEntry::make('phone'),
                    Infolists\Components\TextEntry::make('fax'),
                    Infolists\Components\TextEntry::make('area'),
                    Infolists\Components\TextEntry::make('salesperson.name')->label('Agent'),
                    Infolists\Components\TextEntry::make('credit_term_days')
                        ->label('Credit term')
                        ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? "{$state} days"),
                    Infolists\Components\TextEntry::make('credit_limit')->money('MYR'),
                    Infolists\Components\IconEntry::make('is_credit')->boolean(),
                ])->columns(2),
                Infolists\Components\Tabs\Tab::make('E-Invoice')->schema([
                    Infolists\Components\TextEntry::make('tin')->label('TIN'),
                    Infolists\Components\TextEntry::make('sst_registration_no'),
                    Infolists\Components\TextEntry::make('msic_code'),
                    Infolists\Components\TextEntry::make('einvoice_buyer_name')->label('Buyer name'),
                    Infolists\Components\TextEntry::make('einvoice_address')->columnSpanFull(),
                ])->columns(2),
                // read only: special prices come from the price-list import and accepted quotations only
                Infolists\Components\Tabs\Tab::make('Special price')
                    ->badge(fn (Customer $record) => static::specialPriceCount($record) ?: null)
                    ->schema([
                        Infolists\Components\ViewEntry::make('special_prices')
                            ->hiddenLabel()
                            ->view('filament.infolists.customer-special-prices')
                            ->state(fn (Customer $record) => static::specialPrices($record))
                            ->columnSpanFull(),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    /** The customer's special price rows (one per product and condition, as the price list template's rows). */
    public static function specialPriceCount(Customer $customer): int
    {
        return count(static::specialPrices($customer));
    }

    /**
     * The customer's special prices (by debtor code), as the price list template lays them out: per product, one row
     * per condition (drop-off location and type, pickup location, minimum charges, dates) with its quantity ranges.
     * Same products and conditions as the default price; only the price is the customer's.
     *
     * @return list<array<string, mixed>>
     */
    public static function specialPrices(Customer $customer): array
    {
        static $cache = [];
        $code = trim((string) $customer->code);

        if ($code === '') {
            return [];
        }

        return $cache[$code] ??= collect(UomPriceRows::fromTiers(
            UomRateTier::query()->where('customer_code', $code)->with(['uom:id,name,category', 'location:id,name'])->get()
        ))->map(function (array $row): array {
            $uom = Uom::query()->find($row['uom_id'], ['id', 'category']);
            $category = $uom?->category ?: Uom::MEASUREMENT;
            $money = fn ($value) => 'RM '.number_format((float) $value, 2);

            return $row + [
                'category' => $category,
                'category_label' => Uom::CATEGORIES[$category] ?? 'Measurement',
                'drop_off' => DropOffType::tryFrom((string) $row['drop_off_type'])?->getLabel() ?? DropOffType::Default->getLabel(),
                'ranges' => UomPriceRows::rangeLines($row),
                'minimums' => array_values(array_filter([
                    $row['pickup_min_charge'] !== null ? 'Pick-up '.$money($row['pickup_min_charge']) : null,
                    $row['construction_min_charge'] !== null ? 'Construction site '.$money($row['construction_min_charge']) : null,
                    $row['drop_off_min_charge'] !== null ? 'Drop-off '.$money($row['drop_off_min_charge']) : null,
                ])),
                'effective' => match (true) {
                    filled($row['effective_from']) && filled($row['expires_on']) => Carbon::parse($row['effective_from'])->format('d/m/Y').' – '.Carbon::parse($row['expires_on'])->format('d/m/Y'),
                    filled($row['effective_from']) => 'From '.Carbon::parse($row['effective_from'])->format('d/m/Y'),
                    filled($row['expires_on']) => 'Until '.Carbon::parse($row['expires_on'])->format('d/m/Y'),
                    default => null,
                },
                // "<file> · <debtor code> row N" = the price list import; "Quotation Q…" = an accepted quotation
                'from' => str_starts_with((string) $row['source'], 'Quotation') ? (string) $row['source'] : 'Special price list',
            ];
        })
            ->sortBy([
                fn (array $a, array $b) => array_search($a['category'], array_keys(Uom::CATEGORIES)) <=> array_search($b['category'], array_keys(Uom::CATEGORIES)),
                fn (array $a, array $b) => strcmp((string) $a['product'], (string) $b['product']),
                fn (array $a, array $b) => strcmp((string) $a['location'], (string) $b['location']),
                fn (array $a, array $b) => strcmp((string) $a['drop_off_type'], (string) $b['drop_off_type']),
            ])
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')
                ->label('Account')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('company_name')
                ->label('Company')
                ->searchable()
                ->sortable()
                ->wrap(),
            Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
            Tables\Columns\TextColumn::make('area')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('brn')->label('BRN')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('salesperson.name')->label('Agent')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('is_credit')->boolean()->label('Credit'),
            Tables\Columns\TextColumn::make('credit_term_days')
                ->label('Term')
                ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? $state),
            Tables\Columns\IconColumn::make('portal_approved')->boolean()->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Active' : 'Inactive')
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
