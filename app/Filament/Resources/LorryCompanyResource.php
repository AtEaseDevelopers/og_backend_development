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
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "masterdata.fleet" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

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
                Forms\Components\TextInput::make('company_no')->label('Company no.')->maxLength(50),
                Forms\Components\TextInput::make('pic')->label('PIC')->maxLength(150),
                Forms\Components\TextInput::make('contact')->label('Telephone')->tel()->maxLength(50),
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
                Tables\Columns\TextColumn::make('pic')->label('PIC')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('contact')->label('Telephone')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->label('Email')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('address')->label('Address')->limit(50)->placeholder('—')->toggleable(),
                // its lorries: opens Fleet Vehicles searched by the company (client, 16 Nov 2026)
                Tables\Columns\TextColumn::make('lorries_count')->counts('lorries')->label('Lorries')
                    ->url(fn (LorryCompany $record) => FleetVehicleResource::getUrl('index', ['tableSearch' => $record->name]))
                    ->color('primary'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Delete only for a record without history (client, 16 Nov 2026); a used one is set Inactive instead
                \App\Support\RecordUsage::deleteAction(),
            ]);
    }

    /** The company, then every lorry under it with its driver and attendant (client, 16 Nov 2026). */
    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        $entry = fn (string $name, string $label) => \Filament\Infolists\Components\TextEntry::make($name)->label($label)->placeholder('—');

        return $infolist->schema([
            \Filament\Infolists\Components\Section::make()->schema([
                $entry('code', 'Code'),
                $entry('name', 'Name'),
                $entry('company_no', 'Company no.'),
                $entry('pic', 'PIC'),
                $entry('contact', 'Telephone'),
                $entry('email', 'Email'),
                $entry('address', 'Address')->columnSpan(2),
            ])->columns(4)->compact(),
            \Filament\Infolists\Components\Section::make('Lorries')->schema([
                \Filament\Infolists\Components\ViewEntry::make('lorries')->hiddenLabel()->view('filament.infolists.lorry-company-lorries'),
            ])->compact(),
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
