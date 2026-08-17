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
            Forms\Components\Section::make('Location rate tiers')->schema([
                Forms\Components\Repeater::make('rateTiers')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
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
                    ])
                    ->columns(4)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
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
