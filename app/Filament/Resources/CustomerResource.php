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
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "customers.view" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

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
                    // the view page keeps its cards (user, 16 Nov 2026: one panel read as confusing here; the one-panel layout
                    // is the edit form's only)
                    Infolists\Components\Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->extraAttributes(['class' => 'cv-compact'])->schema([
                    // the cards follow the client's Customer Master Data V2 (user, 16 Nov 2026): Account (with the billing
                    // address, contact and tax of the account), Credit term, Notes
                    Infolists\Components\Section::make('Account')->icon('heroicon-o-identification')->headerActions([static::editCard('account', 'Account', CustomerForm::accountFields())])->schema([
                        Infolists\Components\TextEntry::make('code')->label('Debtor account code'),
                        Infolists\Components\TextEntry::make('default_order_type')->label('Debtor type')->badge()
                            ->state(fn (Customer $record) => CustomerForm::debtorTypes()[$record->default_order_type] ?? ($record->is_credit ? 'Credit' : 'Cash')),
                        Infolists\Components\TextEntry::make('company_name')->label('Company name'),
                        Infolists\Components\TextEntry::make('brn')->label('Company registration no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge()
                            ->formatStateUsing(fn (?string $state) => $state === 'active' ? 'Active' : 'Inactive')
                            ->color(fn (?string $state) => $state === 'active' ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('salesperson.name')->label('Salesperson')->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Billing address & contact')->icon('heroicon-o-map-pin')->headerActions([static::editCard('contact', 'Billing address & contact', CustomerForm::contactFields(relationship: false))])->schema([
                        Infolists\Components\TextEntry::make('address')->label('Billing address')->placeholder('—'),
                        Infolists\Components\TextEntry::make('postcode')->label('Postcode')->placeholder('—'),
                        // the attention list: "name · phone", the default first (row 1 = Attention and Phone 1, row 2 = Phone 2)
                        Infolists\Components\TextEntry::make('pic_list')->label('Attention & phone')
                            ->state(fn (Customer $record) => $record->pics()->orderByDesc('is_default')->orderBy('id')->get()
                                ->map(fn ($pic) => ($pic->name ?: '—').' · '.($pic->phone ?: '—'))->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('mobile')->label('Mobile no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('email')->label('Email address')->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Tax')->icon('heroicon-o-receipt-percent')->headerActions([static::editCard('tax', 'Tax', CustomerForm::taxFields())])->schema([
                        Infolists\Components\TextEntry::make('gst_no')->label('GST no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('sst_registration_no')->label('SST no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('tax_type')->label('SST tax code')->placeholder('—'),
                        Infolists\Components\TextEntry::make('tin')->label('TIN no.')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code')->label('MSIC 1')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code_2')->label('MSIC 2')->placeholder('—'),
                        Infolists\Components\TextEntry::make('msic_code_3')->label('MSIC 3')->placeholder('—'),
                        Infolists\Components\IconEntry::make('charges_sst')->label('Charge SST')->boolean(),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    // the e-invoice buyer details (used by the LHDN submission and the portal profile): shown as what is
                    // sent — the buyer's own value, else the account's
                    Infolists\Components\Section::make('E-invoice buyer')->icon('heroicon-o-document-text')->headerActions([static::editCard('einvoice', 'E-invoice buyer', CustomerForm::einvoiceFields())])->schema([
                        Infolists\Components\TextEntry::make('einvoice_buyer_name')->label('Buyer name')->state(fn (Customer $record) => $record->einvoice_buyer_name ?: $record->company_name)->placeholder('—'),
                        Infolists\Components\TextEntry::make('einvoice_tin')->label('Buyer TIN')->state(fn (Customer $record) => $record->einvoice_tin ?: $record->tin)->placeholder('—'),
                        Infolists\Components\TextEntry::make('einvoice_id')->label('ID')->state(fn (Customer $record) => trim(($record->einvoice_id_type ?: 'BRN').' '.($record->einvoice_id_value ?: $record->brn)))->placeholder('—'),
                        Infolists\Components\TextEntry::make('einvoice_address')->label('Address')->state(fn (Customer $record) => $record->einvoice_address ?: $record->address)->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Credit term')->icon('heroicon-o-banknotes')->headerActions([static::editCard('credit', 'Credit term', CustomerForm::creditFields())])->schema([
                        Infolists\Components\TextEntry::make('credit_term_days')->label('Credit term')
                            ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? "{$state} days"),
                        Infolists\Components\TextEntry::make('credit_limit')->label('Credit limit')->money('MYR'),
                        Infolists\Components\TextEntry::make('aging_on')->label('Aging on')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['invoice_date' => 'Invoice date (I)', 'due_date' => 'Due date (D)'][$state] ?? $state),
                        Infolists\Components\TextEntry::make('statement_type')->label('Statement type')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['open_item' => 'Open item (O)', 'balance_forward' => 'Balance forward (B)', 'no_statement' => 'No statement (N)'][$state] ?? $state),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    Infolists\Components\Section::make('Notes')->icon('heroicon-o-chat-bubble-bottom-center-text')->headerActions([static::editCard('notes', 'Notes', CustomerForm::noteFields())])->schema([
                        Infolists\Components\TextEntry::make('account_opening_date')->label('Account opening date')->date('d/m/Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('mailing_address')->label('Mailing address')->placeholder('—'),
                        Infolists\Components\TextEntry::make('mailing_statement_remark')->label('Mailing statement remarks')->placeholder('—'),
                        Infolists\Components\TextEntry::make('own_note')->label('Internal office note')->placeholder('—'),
                    ])->compact()->inlineLabel()->columns(1)->columnSpan(1),
                    ]),
                ]),
                // read only: special prices come from the price-list import and accepted quotations only
                Infolists\Components\Tabs\Tab::make('Special price')
                    ->badge(fn (Customer $record) => static::specialPriceCount($record) ?: null)
                    ->schema([
                        // the customer's special prices as an Excel file in the client's template layout (user, 10 Nov 2026)
                        Infolists\Components\Actions::make([
                            Infolists\Components\Actions\Action::make('exportSpecialPrice')
                                ->label('Export special price (Excel)')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->action(fn (Customer $record) => \App\Support\SpecialPriceExport::download($record)),
                        ])->alignEnd()->visible(fn (Customer $record) => static::specialPriceCount($record) > 0)->columnSpanFull(),
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
    /**
     * The Contact card's PIC list saved (user, 10 Nov 2026): rows changed, added or removed, blank ones skipped; the
     * first is the default (the customer's attention / phone follow it).
     *
     * @param  list<array{id?: ?int, name?: ?string, phone?: ?string}>  $rows
     */
    public static function savePics(Customer $record, array $rows): void
    {
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            $values = ['name' => trim((string) ($row['name'] ?? '')) ?: null, 'phone' => trim((string) ($row['phone'] ?? '')) ?: null];

            if ($values['name'] === null && $values['phone'] === null) {
                continue;
            }

            $pic = filled($row['id'] ?? null) ? $record->pics()->find($row['id']) : null;
            $pic ? $pic->update($values) : $pic = $record->pics()->create($values);
            $keep[] = $pic->id;
        }

        $record->pics()->whereNotIn('id', $keep)->get()->each->delete();
        // the first row of the list is the default, whatever the saves above chose on the way
        $record->pics()->update(['is_default' => false]);
        if ($keep !== []) {
            $record->pics()->whereKey($keep[0])->update(['is_default' => true]);
        }
        $record->syncContactFromPics();
        \App\Domains\MasterData\Models\ConsignorConsignee::syncFromCustomer($record->fresh());
    }

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

                if ($key === 'contact') {
                    $data['pics'] = $record->pics()->orderByDesc('is_default')->orderBy('id')->get(['id', 'name', 'phone'])->toArray() ?: [['id' => null, 'name' => '', 'phone' => '']];
                }

                // Debtor type: an older customer without one shows what it is
                if ($key === 'account' && ! array_key_exists((string) ($data['default_order_type'] ?? ''), CustomerForm::debtorTypes())) {
                    $data['default_order_type'] = $record->is_credit ? 'term' : 'cash';
                }

                return $data;
            })
            ->action(function (Customer $record, array $data) use ($key, $heading): void {
                if ($key === 'contact') {
                    static::savePics($record, (array) ($data['pics'] ?? []));
                    unset($data['pics']);
                }

                $record->update($data);
                \Filament\Notifications\Notification::make()->title($heading.' saved')->success()->send();
            });
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
                    ($row['extra_drop_charge'] ?? null) !== null ? 'Extra drop-off '.$money($row['extra_drop_charge']).' / consignee' : null,
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
            Tables\Columns\TextColumn::make('salesperson.name')->label('Salesperson')->toggleable(isToggledHiddenByDefault: true),
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
            // Delete only for a record without history (client, 16 Nov 2026); a used one is set Inactive instead
            \App\Support\RecordUsage::deleteAction(),
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
