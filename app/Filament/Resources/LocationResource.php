<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Location;
use App\Filament\Resources\LocationResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master Data → Destinations (user, 29 Oct 2026): the drop-off locations of the price list. The "Quotation column"
 * groups destinations into one column on the quotation PDF, as the client's "S'BAN / MELAKA".
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'destinations';

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Destinations';

    protected static ?string $modelLabel = 'destination';

    protected static ?string $pluralModelLabel = 'destinations';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')->label('Destination')->required()->maxLength(150),
                Forms\Components\TextInput::make('code')->label('Code')->maxLength(50)->placeholder('e.g. SBN'),
                Forms\Components\TextInput::make('state')->label('State')->maxLength(100),
                Forms\Components\TextInput::make('quote_column')
                    ->label('Quotation column')
                    ->maxLength(60)
                    ->placeholder("e.g. S'BAN / MELAKA")
                    ->helperText('Destinations with the same label print as one column on the quotation PDF. Leave empty to print the destination under its own name.'),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Destination')->searchable()->sortable()->weight('semibold'),
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('state')->label('State')->sortable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('quote_column')->label('Quotation column')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('uom_rate_tiers_count')->counts('uomRateTiers')->label('Price rows')->alignCenter()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
