<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Uom;
use App\Filament\Resources\UomResource\Pages;
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

    protected static ?string $navigationLabel = 'UOM';

    protected static ?string $modelLabel = 'UOM';

    protected static ?string $pluralModelLabel = 'UOMs';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('UOM details')->schema([
                Forms\Components\TextInput::make('code')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank to auto-generate from name.'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state, Forms\Get $get) {
                        if (filled($get('code'))) {
                            return;
                        }

                        $set('code', Str::upper(Str::limit(Str::slug($state ?? '', '-'), 50, '')));
                    }),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Location rate tiers')
                ->description('The default price per location and quantity range. A customer\'s special price is not edited here: it comes from the special price list and from accepted quotations (Customer → Special price).')
                ->schema([
                Forms\Components\Repeater::make('rateTiers')
                    // the default price rows only: the customers' special price rows stay as they are
                    ->relationship('rateTiers', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereNull('customer_code'))
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('drop_off_type')
                            ->options(\App\Enums\DropOffType::options())
                            ->default(\App\Enums\DropOffType::Default->value)
                            ->selectablePlaceholder(false),
                        Forms\Components\TextInput::make('min_qty')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('max_qty')
                            ->numeric()
                            ->helperText('Leave empty for "and above".'),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('RM')
                            ->required(),
                        Forms\Components\TextInput::make('pickup_min_charge')->label('Pick-up min.')->numeric()->prefix('RM'),
                        Forms\Components\TextInput::make('construction_min_charge')->label('Construction site min.')->numeric()->prefix('RM'),
                        Forms\Components\TextInput::make('drop_off_min_charge')->label('Drop-off min.')->numeric()->prefix('RM'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : null)
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate_tiers_count')
                    ->counts('rateTiers')
                    ->label('Rate tiers'),
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
