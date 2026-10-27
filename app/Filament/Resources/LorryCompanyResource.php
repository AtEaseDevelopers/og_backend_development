<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\LorryCompany;
use App\Filament\Resources\LorryCompanyResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Master Data → Lorry Companies (user, 28 Oct 2026): simple CRUD; a driver picks one on its form. */
class LorryCompanyResource extends Resource
{
    protected static ?string $model = LorryCompany::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'lorry-companies';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Lorry Companies';

    protected static ?string $modelLabel = 'lorry company';

    protected static ?string $pluralModelLabel = 'lorry companies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Hidden::make('company_id')->default(fn () => CurrentCompany::id()),
                Forms\Components\TextInput::make('code')->label('Code')->maxLength(50)->placeholder('e.g. 4000-A001'),
                Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(150),
                Forms\Components\TextInput::make('contact')->label('Contact')->tel()->maxLength(50),
                Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(255),
                Forms\Components\Textarea::make('address')->label('Address')->rows(2)->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable()->weight('semibold'),
                Tables\Columns\TextColumn::make('contact')->label('Contact')->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->label('Email')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('address')->label('Address')->limit(50)->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('drivers_count')->counts('drivers')->label('Drivers'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLorryCompanies::route('/'),
            'create' => Pages\CreateLorryCompany::route('/create'),
            'edit' => Pages\EditLorryCompany::route('/{record}/edit'),
        ];
    }
}
