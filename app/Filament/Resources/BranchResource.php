<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\BranchResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('company_name')->required(),
            Forms\Components\TextInput::make('company_no'),
            Forms\Components\Textarea::make('address')
                ->rows(3)
                ->helperText('Also the store address: an order whose consignor brings the goods to this branch (Store) uses it as the pickup address.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->helperText('Only active branches are offered as stores on Create order.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('company_name')->searchable(),
                Tables\Columns\TextColumn::make('address')->limit(60)->placeholder('No address yet')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
