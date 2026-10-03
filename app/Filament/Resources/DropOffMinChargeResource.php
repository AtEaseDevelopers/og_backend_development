<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DropOffMinCharge;
use App\Enums\DropOffType;
use App\Filament\Resources\DropOffMinChargeResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Section B: minimum charge per destination drop-off type (formula pending O&G confirmation). */
class DropOffMinChargeResource extends Resource
{
    protected static ?string $model = DropOffMinCharge::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Drop-off Min Charges';

    protected static ?int $navigationSort = 13;

    protected static ?string $modelLabel = 'drop-off minimum charge';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Branch (blank = all branches)')
                    ->options(fn () => Branch::query()->where('is_active', true)->orderBy('code')->pluck('name', 'id')),
                Forms\Components\Select::make('drop_off_type')
                    ->options(DropOffType::options())
                    ->required(),
                Forms\Components\TextInput::make('minimum_charge')->numeric()->prefix('RM')->required(),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\TextInput::make('remarks')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('drop_off_type')->badge(),
                Tables\Columns\TextColumn::make('branch.code')->label('Branch')->placeholder('All'),
                Tables\Columns\TextColumn::make('minimum_charge')->money('MYR'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('remarks')->limit(40),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDropOffMinCharges::route('/'),
            'create' => Pages\CreateDropOffMinCharge::route('/create'),
            'edit' => Pages\EditDropOffMinCharge::route('/{record}/edit'),
        ];
    }
}
