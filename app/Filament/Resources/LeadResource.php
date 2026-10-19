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
    protected static ?string $model = Lead::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Master Data';

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
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
