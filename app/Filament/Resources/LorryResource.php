<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\LorryResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LorryResource extends Resource
{
    protected static ?string $model = Lorry::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
            Forms\Components\Hidden::make('branch_id')->default(fn () => \App\Support\CurrentCompany::branchId())->required(),
            Forms\Components\Placeholder::make('branch_label')->label('Company / branch')
                ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
            Forms\Components\TextInput::make('registration_no')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('type'),
            Forms\Components\TextInput::make('capacity')->numeric(),
            Forms\Components\Select::make('default_driver_id')->relationship('defaultDriver', 'name')->searchable(),
            Forms\Components\Select::make('status')->options([
                'available' => 'Available',
                'in_use' => 'In Use',
                'maintenance' => 'Maintenance',
            ])->default('available'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('registration_no')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('branch.name'),
            Tables\Columns\TextColumn::make('type'),
            Tables\Columns\TextColumn::make('defaultDriver.name')->label('Default Driver'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLorries::route('/'),
            'create' => Pages\CreateLorry::route('/create'),
            'edit' => Pages\EditLorry::route('/{record}/edit'),
        ];
    }
}
