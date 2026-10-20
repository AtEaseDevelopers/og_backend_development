<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Driver;
use App\Filament\Resources\DriverResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        // the driver list's columns (user, 21 Oct 2026): driver, lorry company and lorry, attendant
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
            Forms\Components\Hidden::make('branch_id')->default(fn () => \App\Support\CurrentCompany::branchId()),
            Forms\Components\Section::make('Driver')->schema([
                Forms\Components\Placeholder::make('branch_label')->label('Company / branch')
                    ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
                Forms\Components\TextInput::make('code')->maxLength(50),
                Forms\Components\TextInput::make('name')->label('Driver Name')->required()->maxLength(255),
                Forms\Components\TextInput::make('ic_number')->label('Driver IC')->maxLength(30),
                Forms\Components\TextInput::make('phone')->label('Driver Phone Number')->tel()->maxLength(50),
                Forms\Components\TextInput::make('emergency_contact')->label('Driver Emergency Contact')->maxLength(150)->placeholder('e.g. 0197878888 (WIFE)'),
                Forms\Components\Textarea::make('address')->label('Driver Address')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('type')->options([
                    'internal' => 'Internal',
                    'external' => 'External',
                    'subcontractor' => 'Subcontractor',
                ])->default('internal'),
                Forms\Components\Toggle::make('is_active')->default(true)->inline(false),
            ])->columns(3),
            Forms\Components\Section::make('Lorry')
                ->description('The lorry number, type and tonne also fill the lorry in Master Data → Lorries (created if new) with this driver as its default driver.')
                ->schema([
                    Forms\Components\TextInput::make('lorry_group_code')->label('Lorry Group Code')->maxLength(50),
                    Forms\Components\TextInput::make('lorry_group')->label('Lorry Group Name')->maxLength(150),
                    Forms\Components\TextInput::make('lorry_company_code')->label('Lorry Company Code')->maxLength(50),
                    Forms\Components\TextInput::make('lorry_company')->label('Lorry Company')->maxLength(150),
                    Forms\Components\TextInput::make('lorry_number')->label('Lorry Number')->maxLength(30)->placeholder('e.g. JCM 6456'),
                    Forms\Components\TextInput::make('lorry_type')->label('Lorry Type')->maxLength(50)->placeholder('e.g. 24FT'),
                    Forms\Components\TextInput::make('tonnage')->label('Tonne')->numeric()->minValue(0),
                ])->columns(4),
            Forms\Components\Section::make('Attendant')
                ->description('Leave blank when the lorry has no attendant.')
                ->schema([
                    Forms\Components\TextInput::make('attendant_name')->label('Attendant Name')->maxLength(150),
                    Forms\Components\TextInput::make('attendant_ic')->label('Attendant IC')->maxLength(30),
                    Forms\Components\TextInput::make('attendant_phone')->label('Attendant Phone Number')->tel()->maxLength(50),
                    Forms\Components\TextInput::make('attendant_emergency_contact')->label('Attendant Emergency Contact')->maxLength(150),
                    Forms\Components\Textarea::make('attendant_address')->label('Attendant Address')->rows(2)->columnSpanFull(),
                ])->columns(4),
        ]);
    }

    /**
     * The driver's lorry (number, type, tonne) in Master Data → Lorries: the lorry with that number (spaces and case
     * ignored) is updated, else created; this driver is its default driver. Tonne is kept as capacity in kg.
     */
    public static function syncLorry(\App\Domains\MasterData\Models\Driver $driver): void
    {
        $number = trim((string) $driver->lorry_number);

        if ($number === '') {
            return;
        }

        $lorry = \App\Domains\MasterData\Models\Lorry::query()
            ->whereRaw("REPLACE(UPPER(registration_no), ' ', '') = ?", [strtoupper(preg_replace('/\s+/', '', $number))])
            ->first();
        $fields = array_filter([
            'type' => filled($driver->lorry_type) ? (string) $driver->lorry_type : null,
            'capacity' => $driver->tonnage !== null ? round((float) $driver->tonnage * 1000, 2) : null,
        ], fn ($value) => $value !== null) + ['default_driver_id' => $driver->id];

        if ($lorry) {
            $lorry->update($fields);

            return;
        }

        \App\Domains\MasterData\Models\Lorry::query()->create($fields + [
            'company_id' => $driver->company_id,
            'branch_id' => $driver->branch_id,
            'registration_no' => $number,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('branch.name'),
            Tables\Columns\TextColumn::make('phone'),
            Tables\Columns\TextColumn::make('ic_number')->label('IC')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('lorry_number')->label('Lorry')->description(fn ($record) => collect([$record->lorry_type, $record->tonnage !== null ? rtrim(rtrim(number_format((float) $record->tonnage, 2), '0'), '.').' t' : null])->filter()->implode(' · ') ?: null)->placeholder('—')->searchable(),
            Tables\Columns\TextColumn::make('lorry_company')->label('Lorry company')->description(fn ($record) => $record->lorry_company_code)->placeholder('—')->toggleable(),
            Tables\Columns\TextColumn::make('lorry_group')->label('Lorry group')->description(fn ($record) => $record->lorry_group_code)->placeholder('—')->toggleable(),
            Tables\Columns\TextColumn::make('attendant_name')->label('Attendant')->placeholder('—')->toggleable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
