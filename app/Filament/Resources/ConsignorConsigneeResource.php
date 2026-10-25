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
                        .' · the name, address, PIC and telephone come from the customer: change them on the customer.')
                    ->visible($fromCustomer)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(255)->disabled($fromCustomer),
                Forms\Components\Select::make('type')
                    ->label('Used as')
                    ->options(ConsignorConsignee::TYPES)
                    ->default('both')
                    ->selectablePlaceholder(false)
                    ->required(),
                Forms\Components\TextInput::make('pic_name')->label('PIC name')->maxLength(150)->disabled($fromCustomer),
                Forms\Components\TextInput::make('phone')->label('Telephone number')->tel()->maxLength(50)->disabled($fromCustomer),
                Forms\Components\Textarea::make('address')->label('Address')->rows(2)->columnSpanFull()->disabled($fromCustomer),
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
                    ->columnSpanFull(),
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
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('code', 'like', "%{$search}%"))))->sortable()->weight('semibold')->description(fn (ConsignorConsignee $record) => $record->address),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (ConsignorConsignee $record) => $record->isFromCustomer() ? 'Customer' : 'Created here')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Customer' ? 'info' : 'gray')
                    ->description(fn (ConsignorConsignee $record) => $record->customer?->code),
                Tables\Columns\TextColumn::make('type')->label('Used as')->badge()->formatStateUsing(fn (ConsignorConsignee $record) => $record->typeLabel()),
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
                Tables\Filters\SelectFilter::make('type')->label('Used as')->options(ConsignorConsignee::TYPES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // a customer's row goes with the customer
                Tables\Actions\DeleteAction::make()->hidden(fn (ConsignorConsignee $record) => $record->isFromCustomer()),
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
