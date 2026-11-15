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
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "masterdata.products" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = Location::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'destinations';

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Area';

    protected static ?string $modelLabel = 'area';

    protected static ?string $pluralModelLabel = 'areas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')->label('Area')->required()->maxLength(150),
                Forms\Components\TextInput::make('code')->label('Code')->maxLength(50)->placeholder('e.g. SBN'),
                Forms\Components\TextInput::make('state')->label('State')->maxLength(100),
                Forms\Components\TextInput::make('quote_column')
                    ->label('Quotation column')
                    ->maxLength(60)
                    ->placeholder("e.g. S'BAN / MELAKA")
                    ->helperText('Areas with the same label print as one column on the quotation PDF. Leave empty to print the area under its own name.'),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Area')->searchable()->sortable()->weight('semibold'),
                Tables\Columns\TextColumn::make('state')->label('State')->sortable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('uom_rate_tiers_count')->counts('uomRateTiers')->label('Price rows')->alignCenter()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // only an area without price rows (user, 13 Nov 2026); kept in the database (soft delete), so the
                // orders and CSNs that used it still show its name
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Location $record) => ! $record->hasPriceRows())
                    ->modalHeading(fn (Location $record) => 'Delete area '.$record->name.'?')
                    ->modalDescription('The area is no longer offered on orders, quotations and price lists. Orders and CSNs that used it keep its name.')
                    ->successNotificationTitle('Area deleted'),
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
