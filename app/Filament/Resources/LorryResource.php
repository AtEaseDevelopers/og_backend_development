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

    // Master Data → Lorries lists the Chartered Lorry products now (LorryProductResource, user 21 Oct 2026); this
    // list stays as the fleet vehicles' lorry sizes (kept in step when a lorry product is saved)
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Lorries';

    protected static ?string $modelLabel = 'Chartered Lorry';

    protected static ?string $pluralModelLabel = 'Lorries';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // the lorry sizes the fleet vehicles use; a chartered lorry trip's price is set on its UOM product
            // (Master Data → UOM, category Chartered Lorry, user 21 Oct 2026)
            Forms\Components\Section::make('Chartered lorry details')
                ->description('Lorry sizes used by the fleet vehicles. The price of a chartered lorry trip is set in Master Data → UOM (category Chartered Lorry).')
                ->schema([
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
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
