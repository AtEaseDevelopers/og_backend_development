<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Invoice;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('sourceBranch.name')->label('Branch'),
                Infolists\Components\TextEntry::make('customer.company_name')->label('Customer'),
                Infolists\Components\TextEntry::make('type')->badge(),
                Infolists\Components\TextEntry::make('billing_month'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('subtotal')->money('MYR'),
                Infolists\Components\TextEntry::make('tax_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('rounding_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('total_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('invoice_date')->date(),
                Infolists\Components\TextEntry::make('due_date')->date(),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('lines')
                ->schema([
                    Infolists\Components\TextEntry::make('description'),
                    Infolists\Components\TextEntry::make('amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('consignment_note_id')->label('CSN ID'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('customer.company_name')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('billing_month'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('due_date')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'cash_bill' => 'Cash Bill',
                    'term' => 'Term',
                    'forfeit' => 'Forfeit',
                    'additional' => 'Additional',
                ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => ucfirst(str_replace('_', ' ', $c->value))]
                    )),
                Tables\Filters\SelectFilter::make('source_branch_id')->relationship('sourceBranch', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
