<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\OtherItem;
use App\Filament\Resources\OtherItemResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Master Data → Other Items (user, 16 Nov 2026): the items picked on an Other Invoice; a description only. */
class OtherItemResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = OtherItem::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'other-items';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page, not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Other Items';

    protected static ?string $modelLabel = 'other item';

    protected static ?string $pluralModelLabel = 'Other Items';

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
                // free text (user, 16 Nov 2026); the categories already used are offered while typing, so the same one
                // is written the same way — on an Other Invoice the category is picked first, then its items
                Forms\Components\TextInput::make('category')->label('Category')->maxLength(150)->placeholder('e.g. Rental')
                    ->datalist(fn () => OtherItem::query()->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                        ->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category')->all())
                    ->columnSpan(3),
                Forms\Components\TextInput::make('code')->label('Code')->maxLength(50)->columnSpan(2),
                Forms\Components\TextInput::make('name')->label('Item')->required()->maxLength(255)->placeholder('e.g. Rental Income')->columnSpan(5),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true)->inline(false)->columnSpan(2),
            ])->columns(12),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')->label('Category')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('Item')->searchable()->sortable()->weight('semibold'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Added')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Delete only for an item never used on an invoice; a used one is set Inactive instead
                \App\Support\RecordUsage::deleteAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtherItems::route('/'),
            'create' => Pages\CreateOtherItem::route('/create'),
            'edit' => Pages\EditOtherItem::route('/{record}/edit'),
        ];
    }
}
