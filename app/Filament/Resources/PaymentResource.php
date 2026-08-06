<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Payment;
use App\Filament\Resources\PaymentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payments & Receipts';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('source_branch_id')
                ->relationship('sourceBranch', 'name')
                ->required()
                ->searchable(),
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'company_name')
                ->searchable(),
            Forms\Components\Select::make('consignment_note_id')
                ->relationship('consignmentNote', 'number')
                ->searchable(),
            Forms\Components\Select::make('invoice_id')
                ->relationship('invoice', 'number')
                ->searchable(),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\Select::make('method')
                ->options([
                    'cash' => 'Cash',
                    'ewallet' => 'eWallet',
                    'bank_transfer' => 'Bank Transfer',
                    'online' => 'Online Payment',
                    'counter' => 'Pay at Counter',
                    'cod' => 'COD',
                    'credit' => 'Credit',
                ])
                ->required(),
            Forms\Components\TextInput::make('reference'),
            Forms\Components\Textarea::make('remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('customer.company_name')->searchable(),
                Tables\Columns\TextColumn::make('consignmentNote.number')->label('CSN'),
                Tables\Columns\TextColumn::make('invoice.number')->label('Invoice'),
                Tables\Columns\TextColumn::make('method')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('MYR'),
                Tables\Columns\TextColumn::make('receipt.number')->label('Receipt'),
                Tables\Columns\TextColumn::make('reconciliation_status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('method')->options([
                    'cash' => 'Cash',
                    'cod' => 'COD',
                    'bank_transfer' => 'Bank Transfer',
                    'online' => 'Online',
                ]),
                Tables\Filters\SelectFilter::make('source_branch_id')->relationship('sourceBranch', 'name'),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
        ];
    }
}
