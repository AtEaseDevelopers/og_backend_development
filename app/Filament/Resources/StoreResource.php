<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Store;
use App\Filament\Resources\StoreResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Stores of each branch (Create order → Consignor → Store). */
class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'store';

    /** The current company's stores. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Store')->schema([
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (Branch $b) => [$b->id => $b->code.' — '.$b->name]))
                    ->default(fn () => CurrentCompany::branchId())
                    ->required(),
                Forms\Components\TextInput::make('code')->maxLength(30)->helperText('Optional short code'),
                Forms\Components\TextInput::make('name')->label('Store name')->required()->maxLength(255),
                Forms\Components\Select::make('location_id')
                    ->label('From (price list location)')
                    ->options(fn () => Location::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->helperText('The "From" location of orders picked up at this store.'),
                Forms\Components\Textarea::make('address')->label('Address (pickup location)')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('pic_name')->label('PIC name')->maxLength(255),
                Forms\Components\TextInput::make('pic_phone')->label('Contact number')->tel()->maxLength(50),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Store')->description(fn (Store $s) => $s->code)->searchable(['name', 'code'])->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->badge()->sortable(),
                Tables\Columns\TextColumn::make('address')->wrap()->limit(80)->placeholder('—'),
                Tables\Columns\TextColumn::make('pic_name')->label('PIC')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('pic_phone')->label('Contact number')->placeholder('—'),
                Tables\Columns\TextColumn::make('location.name')->label('From')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
