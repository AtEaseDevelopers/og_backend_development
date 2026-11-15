<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Lead;
use App\Filament\Resources\LeadResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Master Data → Leads (user, 20 Oct 2026): name, contact number, email and address of a possible customer. */
class LeadResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "customers.view" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = Lead::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Leads';

    protected static ?string $modelLabel = 'lead';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema(static::leadFields())->columns(2),
        ]);
    }

    /**
     * The lead's fields (also the "+ New lead" form of Create quotation). PIC name and billing address are what an
     * order needs later.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function leadFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label('Name / company')->required()->maxLength(255),
            Forms\Components\TextInput::make('pic_name')->label('PIC name')->maxLength(255),
            Forms\Components\TextInput::make('contact')->label('Contact number')->tel()->maxLength(50),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\Textarea::make('billing_address')->label('Billing address')->rows(2)->columnSpanFull(),
            Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
            // an inactive lead is no longer offered on Create quotation (client, 16 Nov 2026)
            Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                Tables\Columns\TextColumn::make('pic_name')->label('PIC name')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('contact')->label('Contact number')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('billing_address')->label('Billing address')->limit(50)->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('customer.code')->label('Customer')->placeholder('Not a customer yet')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Added')->date('d/m/Y')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Delete only for a record without history (client, 16 Nov 2026); a used one is set Inactive instead
                \App\Support\RecordUsage::deleteAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
