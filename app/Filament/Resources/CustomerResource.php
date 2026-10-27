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

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Form $form): Form
    {
        return CustomerForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // the details on one tab, a section per part as on the form (user, 25 Oct 2026); special prices on their own tab
            Infolists\Components\Tabs::make('Customer')->tabs([
                Infolists\Components\Tabs\Tab::make('Details')->schema([
                    // compact cards side by side, label beside value (user, 25 Oct 2026), so the page needs little scrolling
                    Infolists\Components\ViewEntry::make('compact_style')->hiddenLabel()->view('filament.infolists.customer-compact-style')->columnSpanFull(),
                    Infolists\Components\Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->extraAttributes(['class' => 'cv-compact'])->schema([
                    Infolists\Components\Section::make('Account')->icon('heroicon-o-identification')->headerActions([static::editCard('account', 'Account', CustomerForm::accountFields())])->schema([
                        Infolists\Components\TextEntry::make('code')->label('Debtor code'),
                        Infolists\Components\TextEntry::make('company_name')->label('Company name'),
                        Infolists\Components\TextEntry::make('brn')->label('Company registration no. (BRN)')->placeholder('—'),
                        Infolists\Components\TextEntry::make('area')->label('Area code')->placeholder('—'),
                        Infolists\Components\TextEntry::make('debtor_type')->label('Debtor type')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => CustomerForm::debtorTypeOptions()[$state] ?? $state),
                        Infolists\Components\TextEntry::make('control_account')->label('Control account')->placeholder('—'),
                        Infolists\Components\TextEntry::make('salesperson.name')->label('Agent')->placeholder('—'),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge()
                            ->formatStateUsing(fn (?string $state) => $state === 'active' ? 'Active' : 'Inactive')
                            ->color(fn (?string $state) => $state === 'active' ? 'success' : 'gray'),
                        Infolists\Components\IconEntry::make('is_group_company')->label('Group company')->boolean(),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Address')->icon('heroicon-o-map-pin')->headerActions([static::editCard('address', 'Address', static::addressCardFields())])->schema([
                        Infolists\Components\TextEntry::make('address')->label('Address')->placeholder('—'),
                        Infolists\Components\TextEntry::make('postcode')->label('Postcode')->placeholder('—'),
                        Infolists\Components\TextEntry::make('delivery_address')->label('Delivery address')->placeholder('—')
                            ->state(fn (Customer $record) => $record->addresses()->where('type', 'delivery')->orderByDesc('is_default')->value('address')),
                        Infolists\Components\TextEntry::make('delivery_postcode')->label('Deliver postcode')->placeholder('—')
                            ->state(fn (Customer $record) => $record->addresses()->where('type', 'delivery')->orderByDesc('is_default')->value('postcode')),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Contact')->icon('heroicon-o-phone')->headerActions([static::editCard('contact', 'Contact', CustomerForm::contactFields())])->schema([
                        Infolists\Components\TextEntry::make('attention')->placeholder('—'),
                        Infolists\Components\TextEntry::make('contact_person_3')->label('Contact person 1')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('contact_person_4')->label('Contact person 2')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('business_nature')->label('Business nature')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('phone')->label('Phone 1')->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone_2')->label('Phone 2')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('phone_3')->label('Phone 3')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('phone_4')->label('Phone 4')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('mobile')->label('Mobile')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('fax')->hidden(fn (?string $state) => blank($state)),
                        Infolists\Components\TextEntry::make('email')->label('Email address')->placeholder('—'),
                        Infolists\Components\TextEntry::make('website')->hidden(fn (?string $state) => blank($state)),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Credit & statement')->icon('heroicon-o-banknotes')->headerActions([static::editCard('credit', 'Credit & statement', CustomerForm::creditFields())])->schema([
                        Infolists\Components\TextEntry::make('display_term')->label('Display term')->placeholder('—'),
                        Infolists\Components\TextEntry::make('credit_term_days')->label('Credit term')
                            ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? "{$state} days"),
                        Infolists\Components\TextEntry::make('aging_on')->label('Aging on')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['invoice_date' => 'Invoice date (I)', 'due_date' => 'Due date (D)'][$state] ?? $state),
                        Infolists\Components\TextEntry::make('statement_type')->label('Statement type')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['open_item' => 'Open item (O)', 'balance_forward' => 'Balance forward (B)', 'no_statement' => 'No statement (N)'][$state] ?? $state),
                        Infolists\Components\IconEntry::make('is_credit')->label('Credit customer')->boolean(),
                        Infolists\Components\TextEntry::make('credit_limit')->money('MYR'),
                        Infolists\Components\TextEntry::make('currency')->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Tax & e-invoice')->icon('heroicon-o-receipt-percent')->headerActions([static::editCard('tax', 'Tax & e-invoice', CustomerForm::taxFields())])->schema([
                        Infolists\Components\TextEntry::make('gst_no')->label('GST no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('sst_registration_no')->label('SST no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('tax_type')->label('Tax code')->placeholder('—'),
                        Infolists\Components\TextEntry::make('tin')->label('TIN no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code')->label('MSIC 1')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code_2')->label('MSIC 2')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code_3')->label('MSIC 3')->placeholder('—'),
                        Infolists\Components\TextEntry::make('einvoice_buyer_name')->label('Buyer name')->placeholder('—'),
                        Infolists\Components\TextEntry::make('einvoice_address')->label('E-invoice address')->placeholder('—')->columnSpanFull(),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Notes')->icon('heroicon-o-chat-bubble-bottom-center-text')->headerActions([static::editCard('notes', 'Notes', CustomerForm::noteFields())])->schema([
                        Infolists\Components\TextEntry::make('own_note')->label('Own note: other email, statement remark')->placeholder('—'),
                        Infolists\Components\TextEntry::make('mailing_statement_remark')->label('Mailing statement remark')->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_note')->label('Own note (payment)')->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    ]),
                ]),
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

    /**
     * Edit one card of the customer view in a modal (user, 25 Oct 2026), instead of the whole customer on the edit page.
     *
     * @param  list<\Filament\Forms\Components\Component>  $fields
     */
    private static function editCard(string $key, string $heading, array $fields): Infolists\Components\Actions\Action
    {
        return Infolists\Components\Actions\Action::make('edit_'.$key)
            ->label('Edit')
            ->icon('heroicon-m-pencil-square')
            ->size('sm')
            ->link()
            ->modalHeading('Edit '.strtolower($heading))
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Save')
            ->visible(fn (Customer $record) => static::canEdit($record))
            ->form($fields)
            ->fillForm(function (Customer $record) use ($key): array {
                $data = $record->attributesToArray();

                if ($key === 'address') {
                    $delivery = $record->addresses()->where('type', 'delivery')->orderByDesc('is_default')->first();
                    $data['delivery_address_text'] = $delivery?->address;
                    $data['delivery_postcode'] = $delivery?->postcode;
                }

                return $data;
            })
            ->action(function (Customer $record, array $data) use ($key, $heading): void {
                if ($key === 'address') {
                    $delivery = trim((string) ($data['delivery_address_text'] ?? ''));

                    if ($delivery !== '') {
                        \App\Domains\MasterData\Models\CustomerAddress::query()->updateOrCreate(
                            ['customer_id' => $record->id, 'type' => 'delivery', 'is_default' => true],
                            ['label' => 'Primary delivery', 'address' => $delivery, 'postcode' => $data['delivery_postcode'] ?? null],
                        );
                    }

                    unset($data['delivery_address_text'], $data['delivery_postcode']);
                }

                $record->update($data);
                \Filament\Notifications\Notification::make()->title($heading.' saved')->success()->send();
            });
    }

    /** @return list<\Filament\Forms\Components\Component> */
    private static function addressCardFields(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(2)->schema([
                \Filament\Forms\Components\Textarea::make('address')->label('Address (address 1 – 4)')->rows(4),
                \Filament\Forms\Components\Textarea::make('delivery_address_text')->label('Delivery address (deliver address 1 – 4)')->rows(4),
                \Filament\Forms\Components\TextInput::make('postcode')->label('Postcode')->maxLength(20),
                \Filament\Forms\Components\TextInput::make('delivery_postcode')->label('Deliver postcode')->maxLength(20),
            ]),
        ];
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

        return $cache[$code] ??= static::priceRows(UomRateTier::query()->where('customer_code', $code)->with(['uom:id,name,category', 'location:id,name'])->get());
    }

    /** The company's default prices (every product), laid out as the special prices. @return list<array<string, mixed>> */
    public static function defaultPrices(): array
    {
        return static::priceRows(UomRateTier::query()->whereNull('customer_code')->whereHas('uom')->with(['uom:id,name,category', 'location:id,name'])->get());
    }

    /** Price rows (one per product and condition) from rate tiers, as the price list template lays them out. */
    public static function priceRows(\Illuminate\Support\Collection $tiers): array
    {
        return collect(UomPriceRows::fromTiers($tiers))->map(function (array $row): array {
            $uom = Uom::query()->find($row['uom_id'], ['id', 'category', 'sub_category']);
            $category = $uom?->category ?: Uom::MEASUREMENT;
            $money = fn ($value) => 'RM '.number_format((float) $value, 2);

            return $row + [
                'category' => $category,
                'category_label' => Uom::CATEGORIES[$category] ?? 'Measurement',
                'sub_category' => (string) ($uom?->sub_category ?? ''),
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
                ->label('Company name')
                ->searchable()
                ->sortable()
                ->wrap(),
            Tables\Columns\TextColumn::make('branch.name')->label('O&G company')->toggleable(isToggledHiddenByDefault: true),
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
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive']),
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
