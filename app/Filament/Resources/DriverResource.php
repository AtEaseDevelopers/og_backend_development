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
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
            Forms\Components\Hidden::make('branch_id')->default(fn () => \App\Support\CurrentCompany::branchId()),
            Forms\Components\Placeholder::make('branch_label')->label('Company / branch')
                ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
            Forms\Components\TextInput::make('code'),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\TextInput::make('ic_number'),
            Forms\Components\Select::make('type')->options([
                'internal' => 'Internal',
                'external' => 'External',
                'subcontractor' => 'Subcontractor',
            ])->default('internal'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code'),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('branch.name'),
            Tables\Columns\TextColumn::make('phone'),
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
