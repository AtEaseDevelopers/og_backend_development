<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Invoice;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages;
use Filament\Facades\Filament;
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
                Tables\Actions\Action::make('previewPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Invoice $record): string => static::pdfUrl($record))
                    ->openUrlInNewTab(),
                static::sendAction(Tables\Actions\Action::make('sendToCustomer')),
            ]);
    }

    /**
     * Section G: email the confirmed Invoice / Cash Bill (PDF attached) to the customer.
     * Shared by the table row and the view page header.
     */
    public static function sendAction(\Filament\Actions\Action|Tables\Actions\Action $action): \Filament\Actions\Action|Tables\Actions\Action
    {
        return $action
            ->label(fn (Invoice $record) => $record->sent_at ? 'Resend to customer' : 'Send to customer')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->visible(fn (Invoice $record) => ! in_array($record->status, ['draft', 'cancelled'], true))
            ->form(fn (Invoice $record) => [
                \Filament\Forms\Components\TextInput::make('email')
                    ->label('Send to')
                    ->email()
                    ->default($record->customer?->email)
                    ->required(),
                \Filament\Forms\Components\Textarea::make('note')->label('Message to customer (optional)')->rows(2),
                \Filament\Forms\Components\Placeholder::make('last')
                    ->label('Last sent')
                    ->content($record->sent_at?->format('d/m/Y H:i') ?? 'Never')
                    ->visible((bool) $record->sent_at),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    $log = app(\App\Domains\Billing\Actions\SendInvoice::class)->execute($record, auth()->user(), $data['email'], $data['note'] ?? null);

                    \Filament\Notifications\Notification::make()
                        ->title($log->status === 'sent' ? 'Invoice emailed to '.$data['email'] : 'Invoice not sent: '.($log->error ?? $log->status))
                        ->{$log->status === 'sent' ? 'success' : 'warning'}()
                        ->send();
                } catch (\Throwable $e) {
                    \Filament\Notifications\Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function pdfUrl(Invoice $record): string
    {
        return route('filament.admin.invoices.pdf', [
            'tenant' => Filament::getTenant(),
            'invoice' => $record,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
