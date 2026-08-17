<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Filament\Resources\LorryResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LorryResource extends Resource
{
    protected static ?string $model = CharteredLorry::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Lorries';

    protected static ?string $modelLabel = 'Chartered Lorry';

    protected static ?string $pluralModelLabel = 'Lorries';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Chartered lorry details')->schema([
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
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('rates_count')
                    ->counts('rates')
                    ->label('Location rates'),
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
            'index' => Pages\ListLorries::route('/'),
            'create' => Pages\CreateLorry::route('/create'),
            'view' => Pages\ViewLorry::route('/{record}'),
            'edit' => Pages\EditLorry::route('/{record}/edit'),
        ];
    }
}
