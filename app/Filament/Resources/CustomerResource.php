<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\CustomerResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
            Forms\Components\Hidden::make('branch_id')->default(fn () => \App\Support\CurrentCompany::branchId())->required(),
            Forms\Components\Placeholder::make('branch_label')->label('Company')
                ->content(fn () => \App\Support\CurrentCompany::get()?->getFilamentName() ?? '—'),
            Forms\Components\TextInput::make('code'),
            Forms\Components\TextInput::make('company_name')->required(),
            Forms\Components\TextInput::make('brn'),
            Forms\Components\TextInput::make('tin'),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\Textarea::make('address')->columnSpanFull(),
            Forms\Components\Toggle::make('is_credit')->default(false),
            Forms\Components\TextInput::make('credit_limit')->numeric()->default(0),
            Forms\Components\TextInput::make('credit_term_days')->numeric()->default(0),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active'),
            Forms\Components\Toggle::make('portal_approved')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('company_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
            Tables\Columns\TextColumn::make('brn'),
            Tables\Columns\IconColumn::make('is_credit')->boolean(),
            Tables\Columns\IconColumn::make('portal_approved')->boolean(),
            Tables\Columns\TextColumn::make('status')->badge(),
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
