<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Form $form): Form
    {
        return CustomerForm::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('Customer')->tabs([
                Infolists\Components\Tabs\Tab::make('Account')->schema([
                    Infolists\Components\TextEntry::make('code')->label('Debtor account'),
                    Infolists\Components\TextEntry::make('company_name'),
                    Infolists\Components\TextEntry::make('brn')->label('Registration no.'),
                    Infolists\Components\TextEntry::make('control_account'),
                    Infolists\Components\TextEntry::make('debtor_type')->formatStateUsing(
                        fn (?string $state) => CustomerForm::debtorTypeOptions()[$state] ?? $state
                    ),
                    Infolists\Components\IconEntry::make('status')
                        ->label('Active')
                        ->boolean()
                        ->getStateUsing(fn (Customer $record) => $record->status === 'active'),
                    Infolists\Components\IconEntry::make('is_group_company')->boolean(),
                ])->columns(2),
                Infolists\Components\Tabs\Tab::make('General')->schema([
                    Infolists\Components\TextEntry::make('address')->label('Billing address')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('email'),
                    Infolists\Components\TextEntry::make('phone'),
                    Infolists\Components\TextEntry::make('fax'),
                    Infolists\Components\TextEntry::make('area'),
                    Infolists\Components\TextEntry::make('salesperson.name')->label('Agent'),
                    Infolists\Components\TextEntry::make('credit_term_days')
                        ->label('Credit term')
                        ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? "{$state} days"),
                    Infolists\Components\TextEntry::make('credit_limit')->money('MYR'),
                    Infolists\Components\IconEntry::make('is_credit')->boolean(),
                ])->columns(2),
                Infolists\Components\Tabs\Tab::make('E-Invoice')->schema([
                    Infolists\Components\TextEntry::make('tin')->label('TIN'),
                    Infolists\Components\TextEntry::make('sst_registration_no'),
                    Infolists\Components\TextEntry::make('msic_code'),
                    Infolists\Components\TextEntry::make('einvoice_buyer_name')->label('Buyer name'),
                    Infolists\Components\TextEntry::make('einvoice_address')->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')
                ->label('Account')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('company_name')
                ->label('Company')
                ->searchable()
                ->sortable()
                ->wrap(),
            Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
            Tables\Columns\TextColumn::make('area')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('brn')->label('Reg. no.')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('salesperson.name')->label('Agent')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\IconColumn::make('is_credit')->boolean()->label('Credit'),
            Tables\Columns\TextColumn::make('credit_term_days')
                ->label('Term')
                ->formatStateUsing(fn ($state) => CustomerForm::creditTermOptions()[$state] ?? $state),
            Tables\Columns\IconColumn::make('portal_approved')->boolean()->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Active' : 'Inactive')
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
