<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\SaLocation;
use App\Filament\Resources\SaLocationResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Section A / H: SA locations with their CSN prefix. */
class SaLocationResource extends Resource
{
    protected static ?string $model = SaLocation::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'SA Locations';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'SA location';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (Branch $b) => [$b->id => $b->code.' — '.$b->name]))
                    ->default(fn () => CurrentCompany::branchId())
                    ->required(),
                Forms\Components\TextInput::make('code')->required()->maxLength(20)->helperText('Unique within the branch'),
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\TextInput::make('csn_prefix')
                    ->label('CSN prefix')
                    ->required()
                    ->maxLength(10)
                    ->helperText('Printed on CSN numbers, e.g. KLS → KLS-CSN-202610-0001'),
                Forms\Components\Textarea::make('address')->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('branch.code')->label('Branch')->badge(),
                Tables\Columns\TextColumn::make('csn_prefix')->label('CSN prefix')->badge()->color('info'),
                Tables\Columns\TextColumn::make('salespersons_count')->counts('salespersons')->label('Salespersons'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->label('Branch')->relationship('branch', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaLocations::route('/'),
            'create' => Pages\CreateSaLocation::route('/create'),
            'edit' => Pages\EditSaLocation::route('/{record}/edit'),
        ];
    }
}
