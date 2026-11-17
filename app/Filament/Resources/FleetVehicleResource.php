<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\FleetVehicleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FleetVehicleResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "masterdata.fleet" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = Lorry::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, Fleet & dispatch (user, 25 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    // "Fleet Vehicles" is named Lorry Database (client, 17 Nov 2026); it is the main page to search a lorry and see its company, its driver and
    // attendant with their phone numbers
    protected static ?string $navigationLabel = 'Lorry Database';

    protected static ?string $modelLabel = 'Lorry';

    protected static ?string $pluralModelLabel = 'Lorry Database';

    protected static ?string $slug = 'fleet-vehicles';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['lorryCompany', 'defaultDriver']);
    }

    /** "1.5" for a capacity kept in kg (the driver form's Tonne fills it the same way). */
    private static function tonnes(mixed $kg): ?string
    {
        return $kg === null || $kg === '' ? null : rtrim(rtrim(number_format((float) $kg / 1000, 3, '.', ''), '0'), '.');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
                Forms\Components\Hidden::make('branch_id')->default(fn () => \App\Support\CurrentCompany::branchId())->required(),
                Forms\Components\TextInput::make('registration_no')->label('Lorry no.')->required()->unique(ignoreRecord: true)->maxLength(30)->placeholder('e.g. JCM 6456'),
                // how many ft, typed (client, 16 Nov 2026); the chartered lorry sizes are offered while typing
                Forms\Components\TextInput::make('type')->label('Lorry type (ft)')->maxLength(50)->placeholder('e.g. 24 FT')
                    ->datalist(fn () => CharteredLorry::query()->where('is_active', true)->orderBy('name')->pluck('name')->all()),
                Forms\Components\TextInput::make('capacity')->label('Capacity (tonne)')->numeric()->minValue(0)
                    ->formatStateUsing(fn ($state) => static::tonnes($state))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? round((float) $state * 1000, 2) : null),
                Forms\Components\Select::make('lorry_company_id')->label('Lorry company')
                    ->options(fn () => \App\Domains\MasterData\Models\LorryCompany::query()->where('is_active', true)
                        ->when(\App\Support\CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                        ->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label()]))
                    ->searchable(),
                // the lorry first, then its driver; the driver's and the attendant's details are kept on the driver
                Forms\Components\Select::make('default_driver_id')->label('Driver')->relationship('defaultDriver', 'name')->searchable()->preload()
                    ->helperText('The attendant and the phone numbers come from the driver (Master Data → Drivers).'),
                Forms\Components\Select::make('status')->options([
                    'available' => 'Available',
                    'in_use' => 'In Use',
                    'maintenance' => 'Maintenance',
                ])->default('available'),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true)->inline(false),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('registration_no')->label('Lorry no.')->searchable()->sortable()->weight('semibold'),
            Tables\Columns\TextColumn::make('type')->label('Type')->placeholder('—'),
            Tables\Columns\TextColumn::make('capacity')->label('Tonne')->formatStateUsing(fn ($state) => static::tonnes($state))->placeholder('—'),
            Tables\Columns\TextColumn::make('lorryCompany.name')->label('Lorry company')->searchable()->placeholder('—'),
            Tables\Columns\TextColumn::make('defaultDriver.name')->label('Driver')->searchable()->placeholder('—'),
            Tables\Columns\TextColumn::make('defaultDriver.phone')->label('Driver mobile')->searchable()->placeholder('—'),
            Tables\Columns\TextColumn::make('defaultDriver.attendant_name')->label('Attendant')->searchable()->placeholder('—'),
            Tables\Columns\TextColumn::make('defaultDriver.attendant_phone')->label('Attendant mobile')->searchable()->placeholder('—'),
            Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
        ])->defaultSort('registration_no')->actions([
            // View: the lorry with its company, driver and attendant in one window
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            // Delete only for a record without history (client, 16 Nov 2026); a used one is set Inactive instead
            \App\Support\RecordUsage::deleteAction(),
        ]);
    }

    /** The lorry, its company, its driver and the attendant on one screen (client, 16 Nov 2026). */
    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        $entry = fn (string $name, string $label) => \Filament\Infolists\Components\TextEntry::make($name)->label($label)->placeholder('—');

        return $infolist->schema([
            \Filament\Infolists\Components\Section::make('Lorry')->schema([
                $entry('registration_no', 'Lorry no.'),
                $entry('type', 'Lorry type'),
                $entry('capacity', 'Capacity (tonne)')->formatStateUsing(fn ($state) => static::tonnes($state)),
                \Filament\Infolists\Components\IconEntry::make('is_active')->label('Active')->boolean(),
            ])->columns(4)->compact(),
            \Filament\Infolists\Components\Section::make('Lorry company')->schema([
                $entry('lorryCompany.name', 'Name'),
                $entry('lorryCompany.pic', 'PIC'),
                $entry('lorryCompany.contact', 'Telephone'),
                $entry('lorryCompany.email', 'Email'),
            ])->columns(4)->compact(),
            \Filament\Infolists\Components\Section::make('Driver')->schema([
                $entry('defaultDriver.name', 'Name'),
                $entry('defaultDriver.ic_number', 'IC'),
                $entry('defaultDriver.phone', 'Mobile no.'),
                $entry('defaultDriver.emergency_contact', 'Emergency contact'),
                $entry('defaultDriver.address', 'Address')->columnSpanFull(),
            ])->columns(4)->compact(),
            \Filament\Infolists\Components\Section::make('Attendant')->schema([
                $entry('defaultDriver.attendant_name', 'Name'),
                $entry('defaultDriver.attendant_ic', 'IC'),
                $entry('defaultDriver.attendant_phone', 'Mobile no.'),
                $entry('defaultDriver.attendant_emergency_contact', 'Emergency contact'),
                $entry('defaultDriver.attendant_address', 'Address')->columnSpanFull(),
            ])->columns(4)->compact(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVehicles::route('/'),
            'create' => Pages\CreateFleetVehicle::route('/create'),
            'edit' => Pages\EditFleetVehicle::route('/{record}/edit'),
        ];
    }
}
