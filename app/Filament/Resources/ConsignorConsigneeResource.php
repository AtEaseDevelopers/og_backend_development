<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\ConsignorConsignee;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Filament\Resources\ConsignorConsigneeResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Master Data → Consignor / Consignee (user, 21 Oct 2026): saved consignors and consignees (name, address, PIC,
 * telephone) tagged to customers. Create order lists them on each consignor & consignee block. Every customer is
 * listed too (user, 25 Oct 2026): its name, address, PIC and telephone come from the customer and are changed there.
 */
class ConsignorConsigneeResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "customers.view" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = ConsignorConsignee::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'consignors-consignees';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Consignor / Consignee';

    protected static ?string $modelLabel = 'consignor / consignee';

    protected static ?string $pluralModelLabel = 'consignors / consignees';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('customer:id,code,company_name')->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    public static function form(Form $form): Form
    {
        // a customer's row: name, address, PIC and telephone follow the customer
        $fromCustomer = fn (?ConsignorConsignee $record) => (bool) $record?->isFromCustomer();

        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Placeholder::make('from_customer')
                    ->label('Customer')
                    ->content(fn (?ConsignorConsignee $record) => trim(($record?->customer?->code ? $record->customer->code.' — ' : '').$record?->customer?->company_name)
                        .' · made from this customer; its details are its own and can be changed here (the customer is not changed).')
                    ->visible($fromCustomer)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(255),
                // no "Used as" (client, 16 Nov 2026): every record is offered as a consignor and as a consignee
                Forms\Components\Hidden::make('type')->default('both')->dehydrateStateUsing(fn () => 'both'),
                Forms\Components\TextInput::make('pic_name')->label('PIC name')->required()->maxLength(150),
                Forms\Components\TextInput::make('phone')->label('Telephone number')->tel()->required()->maxLength(50),
                Forms\Components\Textarea::make('address')->label('Address')->required()->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('location_id')
                    ->label('Area (price list location)')
                    ->helperText('Optional: filled as From (consignor) or To (consignee) on Create order, so the price follows.')
                    ->options(fn () => Location::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Forms\Components\Select::make('customers')
                    ->label('Customer tagging')
                    ->helperText('The customers this consignor / consignee is used for: Create order lists it first for them.')
                    ->relationship('customers', 'company_name', fn (Builder $query) => $query
                        ->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id))
                        ->orderBy('company_name'))
                    ->getOptionLabelFromRecordUsing(fn (Customer $customer) => trim(($customer->code ? $customer->code.' — ' : '').$customer->company_name))
                    ->multiple()
                    ->searchable()
                    ->extraAttributes(['class' => 'og-tag-select'])
                    ->columnSpanFull(),
                // "Search tagged customers": marks the matching tags in the field above (user, 13 Nov 2026)
                Forms\Components\View::make('filament.forms.components.tag-search')->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('code', 'like', "%{$search}%"))))->sortable()->weight('semibold')
                    // the address shortened to one short line, so the table needs no sideways scroll (user, 16 Nov 2026);
                    // the whole address shows on hovering the cell
                    ->description(fn (ConsignorConsignee $record) => filled($record->address) ? \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $record->address)), 45) : null)
                    ->tooltip(fn (ConsignorConsignee $record) => filled($record->address) ? trim((string) preg_replace('/\s+/', ' ', (string) $record->address)) : null),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (ConsignorConsignee $record) => $record->isFromCustomer() ? 'Customer' : 'Created here')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Customer' ? 'info' : 'gray')
                    ->description(fn (ConsignorConsignee $record) => $record->customer?->code),
                Tables\Columns\TextColumn::make('pic_name')->label('PIC')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telephone')->placeholder('—'),
                Tables\Columns\TextColumn::make('location.name')->label('Area')->placeholder('—'),
                Tables\Columns\TextColumn::make('customers.company_name')->label('Customers')->badge()->limitList(3)->expandableLimitedList()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Source')
                    ->options(['customer' => 'Customers', 'created' => 'Created here'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'customer' => $query->whereNotNull('customer_id'),
                        'created' => $query->whereNull('customer_id'),
                        default => $query,
                    }),
            ])
            ->actions([
                // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // a customer's row goes with the customer
                \App\Support\RecordUsage::deleteAction()->hidden(fn (ConsignorConsignee $record) => $record->isFromCustomer() || \App\Support\RecordUsage::isUsed($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsignorConsignees::route('/'),
            'create' => Pages\CreateConsignorConsignee::route('/create'),
            'edit' => Pages\EditConsignorConsignee::route('/{record}/edit'),
        ];
    }
}
