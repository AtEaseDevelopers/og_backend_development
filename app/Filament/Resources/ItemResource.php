<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\ItemCategory;
use App\Filament\Resources\ItemResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('category', fn (Builder $query) => $query->where('name', '!=', 'Chartered Lorry'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Item details')->schema([
                Forms\Components\Hidden::make('item_category_id')
                    ->default(fn () => ItemCategory::query()->where('name', 'Transport Items')->value('id'))
                    ->required(),
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
                Forms\Components\Select::make('default_uom')
                    ->label('Default UOM')
                    ->options(fn () => \App\Domains\MasterData\Models\Uom::query()
                        ->orderBy('name')
                        ->pluck('name', 'code'))
                    ->searchable(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Location rates')->schema([
                Forms\Components\Repeater::make('rates')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->prefix('RM')
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('default_uom')
                    ->label('UOM')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rates_count')
                    ->counts('rates')
                    ->label('Rates'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view' => Pages\ViewItem::route('/{record}'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
