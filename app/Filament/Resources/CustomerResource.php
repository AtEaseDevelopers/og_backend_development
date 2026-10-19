<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\CharteredLorryRate;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
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
                    Infolists\Components\TextEntry::make('brn')->label('Registration no.'),
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

    public static function specialPriceCount(Customer $customer): int
    {
        $code = trim((string) $customer->code);

        return $code === '' ? 0 : UomRateTier::query()->where('customer_code', $code)->count()
            + CharteredLorryRate::query()->where('customer_code', $code)->count();
    }

    /**
     * The customer's special prices (by debtor code): products per destination and quantity range, and chartered
     * lorries per destination.
     *
     * @return list<array{product: string, kind: string, destination: string, pickup: ?string, drop_off: string, quantity: string, price: float, minimums: list<string>, effective: ?string, source: string}>
     */
    public static function specialPrices(Customer $customer): array
    {
        $code = trim((string) $customer->code);

        if ($code === '') {
            return [];
        }

        $money = fn ($value) => 'RM '.number_format((float) $value, 2);
        $effective = fn ($row) => match (true) {
            $row->effective_from && $row->expires_on => $row->effective_from->format('d/m/Y').' – '.$row->expires_on->format('d/m/Y'),
            (bool) $row->effective_from => 'From '.$row->effective_from->format('d/m/Y'),
            (bool) $row->expires_on => 'Until '.$row->expires_on->format('d/m/Y'),
            default => null,
        };
        // "<file> · <debtor code> row N" = the price-list import; "Quotation Q00003" = an accepted quotation
        $source = fn (?string $value) => str_starts_with((string) $value, 'Quotation') ? (string) $value : 'Special price list';
        $dropOff = fn (?string $value) => DropOffType::tryFrom((string) $value)?->getLabel() ?? DropOffType::Default->getLabel();

        $products = UomRateTier::query()->where('customer_code', $code)->with(['uom:id,name', 'location:id,name'])->get()
            ->map(fn (UomRateTier $tier) => [
                'product' => (string) ($tier->uom?->name ?? '—'),
                'kind' => 'Product',
                'destination' => (string) ($tier->location?->name ?? '—'),
                'pickup' => filled($tier->pickup_location) ? (string) $tier->pickup_location : null,
                'drop_off' => $dropOff($tier->drop_off_type),
                'quantity' => $tier->rangeLabel(),
                'price' => (float) $tier->price,
                'minimums' => array_values(array_filter([
                    $tier->pickup_min_charge !== null ? 'Pick-up '.$money($tier->pickup_min_charge) : null,
                    $tier->construction_min_charge !== null ? 'Construction site '.$money($tier->construction_min_charge) : null,
                    $tier->drop_off_min_charge !== null ? 'Drop-off '.$money($tier->drop_off_min_charge) : null,
                ])),
                'effective' => $effective($tier),
                'source' => $source($tier->source),
                'sort' => [(string) $tier->location?->name, (string) $tier->uom?->name, (float) $tier->min_qty],
            ]);

        $lorries = CharteredLorryRate::query()->where('customer_code', $code)->with(['charteredLorry:id,name', 'location:id,name'])->get()
            ->map(fn (CharteredLorryRate $rate) => [
                'product' => (string) ($rate->charteredLorry?->name ?? '—'),
                'kind' => 'Chartered lorry',
                'destination' => (string) ($rate->location?->name ?? '—'),
                'pickup' => filled($rate->pickup_location) ? (string) $rate->pickup_location : null,
                'drop_off' => $dropOff($rate->drop_off_type),
                'quantity' => 'Per trip',
                'price' => (float) $rate->price,
                'minimums' => [],
                'effective' => $effective($rate),
                'source' => $source($rate->source),
                'sort' => [(string) $rate->location?->name, 'zz'.$rate->charteredLorry?->name, 0.0],
            ]);

        return $products->concat($lorries)
            ->sortBy([fn ($a, $b) => $a['sort'] <=> $b['sort']])
            ->map(fn (array $row) => array_diff_key($row, ['sort' => true]))
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
            Tables\Columns\TextColumn::make('brn')->label('Reg. no.')->toggleable(isToggledHiddenByDefault: true),
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
