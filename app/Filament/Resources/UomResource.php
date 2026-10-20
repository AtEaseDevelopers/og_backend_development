<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use App\Filament\Resources\UomResource\Pages;
use App\Services\Imports\ClientPriceListImporter;
use App\Support\UomPriceRows;
use App\Domains\MasterData\Models\CharteredLorry;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UomResource extends Resource
{
    protected static ?string $model = Uom::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'UOM';

    protected static ?string $modelLabel = 'UOM';

    protected static ?string $pluralModelLabel = 'UOMs';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * The menu's category (user, 21 Oct 2026, as the price list template's Category): UOM = Measurement, Items =
     * Item (ItemProductResource), Lorries = Chartered Lorry (LorryProductResource). All are priced the same way.
     */
    public static function category(): string
    {
        return Uom::MEASUREMENT;
    }

    public static function getEloquentQuery(): Builder
    {
        $category = static::category();

        // the old price list's products (no category) are measurement products
        return parent::getEloquentQuery()->where(fn (Builder $query) => $query
            ->where('category', $category)
            ->when($category === Uom::MEASUREMENT, fn (Builder $q) => $q->orWhereNull('category')));
    }

    /** The size and weight limit columns of the price list template. */
    public const LIMITS = [
        'min_lwh' => 'Min L+W+H (CM)',
        'max_lwh' => 'Max L+W+H (CM)',
        'min_length' => 'Min Length, L (CM)',
        'max_length' => 'Max Length, L (CM)',
        'max_width' => 'Max Width, W (CM)',
        'max_height' => 'Max Height, H (CM)',
        'min_weight' => 'Min Weight (KG)',
        'max_weight' => 'Max Weight (KG)',
    ];

    /**
     * The template columns a category's rows have data in (both price list files, 21 Oct 2026): Measurement and Item
     * rows use every column (some Item rows have a length, width or L+W+H too); Chartered Lorry rows only the item,
     * maximum weight, pickup and drop-off location, drop-off type, pick-up and construction site minimum charges,
     * effective date and one quantity with its price.
     *
     * @return array{limits: list<string>, minimums: list<string>, expiry: bool, ranges: int, quantity_to: bool, sub_category: bool}
     */
    public static function columns(): array
    {
        return static::category() === Uom::LORRY
            ? ['limits' => ['max_weight'], 'minimums' => ['pickup_min_charge', 'construction_min_charge'], 'expiry' => false, 'ranges' => 1, 'quantity_to' => false, 'sub_category' => false]
            : ['limits' => array_keys(self::LIMITS), 'minimums' => ['pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge'], 'expiry' => true, 'ranges' => 5, 'quantity_to' => true, 'sub_category' => true];
    }

    /**
     * The form follows the client's price list template (user, 21 Oct 2026): the product (sub-category, item and its
     * limits), then its default price rows, each a condition with its quantity ranges, with the columns its category
     * uses in the template.
     */
    public static function form(Form $form): Form
    {
        $columns = static::columns();
        $lorry = static::category() === Uom::LORRY;

        return $form->schema([
            Forms\Components\Section::make(Uom::CATEGORIES[static::category()])
                ->description(match (static::category()) {
                    Uom::ITEM => 'Category Item, as in the price list template.',
                    Uom::LORRY => 'Category Chartered Lorry: one lorry trip of this size, as in the price list template\'s Chartered Lorry sheet.',
                    default => 'Category Measurement, as in the price list template.',
                })
                ->schema(array_values(array_filter([
                    Forms\Components\Hidden::make('category')->default(static::category()),
                    $columns['sub_category'] ? Forms\Components\TextInput::make('sub_category')
                        ->label('Sub-Category')
                        ->required()
                        ->maxLength(100)
                        ->datalist(fn () => Uom::query()->where('category', static::category())->whereNotNull('sub_category')->distinct()->orderBy('sub_category')->pluck('sub_category')->all()) : null,
                    Forms\Components\TextInput::make('base_item')
                        ->label('Item')
                        ->maxLength(150)
                        ->required($lorry)
                        ->helperText($lorry ? 'The lorry size, e.g. 10 FT Lorry.' : 'Leave blank to use the sub-category.'),
                    $lorry ? null : Forms\Components\TextInput::make('name')
                        ->label('Name on orders')
                        ->maxLength(255)
                        ->helperText('Leave blank to build it from the item and its limits.'),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                ])))->columns(3),
            Forms\Components\Section::make($lorry ? 'Weight limit' : 'Size and weight limits')
                ->schema(array_map(
                    fn (string $column) => Forms\Components\TextInput::make($column)->label(self::LIMITS[$column])->numeric()->minValue(0),
                    $columns['limits'],
                ))
                ->columns(4),
            Forms\Components\Section::make('Default price')
                ->description(($lorry
                    ? 'One row per drop-off location, as in the price list template: the price of one trip.'
                    : 'One row per condition, as in the price list template. Quantity ranges A–E: the price of the range that holds the order quantity is the unit price for the whole quantity; leave "Quantity To" empty for "and above".')
                    .' A customer\'s special price is not edited here: it comes from the special price list and from accepted quotations (Customer → Special price).')
                ->schema([
                    Forms\Components\Repeater::make('price_rows')
                        ->hiddenLabel()
                        ->schema(static::priceRowFields())
                        ->itemLabel(fn (array $state): ?string => static::priceRowLabel($state))
                        ->addActionLabel('Add price row')
                        // saved rows show as one line each (location · drop-off type · prices); a new row opens
                        ->collapsed(fn (?Forms\ComponentContainer $item) => filled($item?->getRawState()['source'] ?? null))
                        ->reorderable(false)
                        ->defaultItems(1)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** @return array<int, Forms\Components\Component> */
    protected static function priceRowFields(): array
    {
        $columns = static::columns();
        $money = fn (string $name, string $label) => Forms\Components\TextInput::make($name)->label($label)->numeric()->minValue(0)->prefix('RM');
        $ranges = [];

        foreach (array_slice(UomPriceRows::RANGES, 0, $columns['ranges']) as $i => $letter) {
            $upper = strtoupper($letter);
            $previous = $i > 0 ? UomPriceRows::RANGES[$i - 1] : null;

            $from = Forms\Components\TextInput::make("range_{$letter}_from")
                ->label("Quantity From {$upper}")
                ->numeric()
                ->minValue(0)
                ->requiredWith("range_{$letter}_price");
            $to = $columns['quantity_to'] ? Forms\Components\TextInput::make("range_{$letter}_to")
                ->label("Quantity To {$upper}")
                ->numeric()
                ->placeholder('and above')
                ->rules([fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $letter) {
                    $start = $get("range_{$letter}_from");

                    if (is_numeric($value) && is_numeric($start) && (float) $value < (float) $start) {
                        $fail('Must not be less than Quantity From.');
                    }
                }]) : null;
            $price = $money("range_{$letter}_price", "Price for This Range (RM) {$upper}")
                ->requiredWith("range_{$letter}_from");

            if ($previous === null) {
                $from->required()->default(1);
                $price->required();
            } else {
                $prevUpper = strtoupper($previous);
                $from->rules([fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $previous, $prevUpper) {
                    if (! is_numeric($value)) {
                        return;
                    }

                    $prevTo = $get("range_{$previous}_to");

                    if (! is_numeric($get("range_{$previous}_from"))) {
                        $fail("Fill range {$prevUpper} first.");
                    } elseif (! is_numeric($prevTo)) {
                        $fail("Range {$prevUpper} has no Quantity To (and above), so no range can follow it.");
                    } elseif ((float) $value <= (float) $prevTo) {
                        $fail("Must be more than Quantity To {$prevUpper} ({$prevTo}).");
                    }
                }]);
            }

            array_push($ranges, ...array_filter([$from, $to, $price]));
        }

        $minimumLabels = [
            'pickup_min_charge' => 'Pick Up Minimum Charge (RM)',
            'construction_min_charge' => 'Construction Site Drop Off Minimum Charge (RM)',
            'drop_off_min_charge' => 'Drop Off Minimum Charge (RM)',
        ];

        return [
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
                Forms\Components\TextInput::make('pickup_location')
                    ->label('Pickup Location')
                    ->maxLength(150)
                    ->datalist(fn () => UomRateTier::query()->whereNotNull('pickup_location')->distinct()->orderBy('pickup_location')->pluck('pickup_location')->all()),
                Forms\Components\Select::make('location_id')
                    ->label('Drop-Off Location')
                    ->options(fn () => static::locationNames())
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('drop_off_type')
                    ->label('Drop-Off Type')
                    ->options(DropOffType::options())
                    ->default(DropOffType::Default->value)
                    ->selectablePlaceholder(false)
                    ->required(),
            ]),
            Forms\Components\Grid::make(5)->schema(array_values(array_filter([
                ...array_map(fn (string $column) => $money($column, $minimumLabels[$column]), $columns['minimums']),
                Forms\Components\DatePicker::make('effective_from')->label('Effective Date')->native(false)->displayFormat('d/m/Y'),
                $columns['expiry'] ? Forms\Components\DatePicker::make('expires_on')->label('Expiry Date')->native(false)->displayFormat('d/m/Y')->afterOrEqual('effective_from') : null,
            ]))),
            Forms\Components\Fieldset::make($columns['ranges'] > 1 ? 'Quantity ranges' : 'Quantity and price')
                ->schema($ranges)
                ->columns($columns['quantity_to'] ? 3 : 2),
            Forms\Components\Hidden::make('source'),
        ];
    }

    /** "Seremban · Default · 1 → RM 14.00, 2–9 → RM 11.00, …" */
    protected static function priceRowLabel(array $state): ?string
    {
        $location = static::locationNames()[$state['location_id'] ?? 0] ?? null;

        if (! $location) {
            return 'New price row';
        }

        $type = DropOffType::tryFrom((string) ($state['drop_off_type'] ?? ''))?->getLabel() ?? DropOffType::Default->getLabel();

        return implode(' · ', array_filter([$location, $type, implode(', ', UomPriceRows::rangeLines($state))]));
    }

    /** @return array<int, string> */
    protected static function locationNames(): array
    {
        static $names = null;

        return $names ??= Location::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The product fields to save: the menu's category, only the limits its category has; a blank name is built
     * from the item and its limits (a lorry is named as its item, "10 FT Lorry").
     */
    public static function productData(array $data): array
    {
        $data['category'] = ($data['category'] ?? null) ?: static::category();
        $columns = static::columns();

        foreach (array_keys(self::LIMITS) as $column) {
            if (! in_array($column, $columns['limits'], true)) {
                $data[$column] = null;
            }
        }

        $data['sub_category'] = $columns['sub_category']
            ? (trim((string) ($data['sub_category'] ?? '')) ?: null)
            : 'Chartered Lorry';
        $data['base_item'] = trim((string) ($data['base_item'] ?? '')) ?: $data['sub_category'];

        if ($data['category'] === Uom::LORRY) {
            $data['name'] = $data['base_item'];
        } elseif (blank($data['name'] ?? null)) {
            $bands = [];

            foreach (array_keys(self::LIMITS) as $column) {
                $bands[$column] = is_numeric($data[$column] ?? null) ? (float) $data[$column] : null;
            }

            $data['name'] = app(ClientPriceListImporter::class)->productName((string) ($data['base_item'] ?? 'Product'), $bands);
        }

        return $data;
    }

    /** A new product's code (not shown: products are known by their name; older order records keep the code). */
    public static function uniqueCode(string $name, mixed $ignoreId = null): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 45, '')) ?: 'PRODUCT';
        $code = $base;
        $n = 2;

        while (Uom::query()->where('code', $code)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $code = $base.'_'.$n++;
        }

        return $code;
    }

    /** A chartered lorry product is also a lorry size the fleet vehicles can use (Fleet → vehicles). */
    public static function productSaved(Uom $uom): void
    {
        if ($uom->category !== Uom::LORRY || CharteredLorry::query()->where('name', $uom->name)->exists()) {
            return;
        }

        CharteredLorry::query()->create([
            'name' => $uom->name,
            'code' => Str::upper(Str::limit(Str::slug($uom->name, '_'), 50, '')),
            'max_weight' => $uom->max_weight,
            'is_active' => $uom->is_active,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                // every product of a menu has its category; a lorry has no sub-category
                Tables\Columns\TextColumn::make('sub_category')->label('Sub-category')->placeholder('—')->searchable()->sortable()->toggleable()
                    ->visible(fn () => static::columns()['sub_category']),
                Tables\Columns\TextColumn::make('rate_tiers_count')
                    ->counts('rateTiers')
                    ->label('Price ranges'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUoms::route('/'),
            'create' => Pages\CreateUom::route('/create'),
            'view' => Pages\ViewUom::route('/{record}'),
            'edit' => Pages\EditUom::route('/{record}/edit'),
        ];
    }
}
