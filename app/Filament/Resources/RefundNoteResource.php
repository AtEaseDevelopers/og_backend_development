<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\CreateRefundNote;
use App\Domains\Billing\Models\RefundNote;
use App\Filament\Resources\RefundNoteResource\Pages;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Section F: refund notes created on overpayment; completed notes join the AutoCount sync. */
class RefundNoteResource extends Resource
{
    protected static ?string $model = RefundNote::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Refund Notes';

    protected static ?int $navigationSort = 24;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('quotation.number')->label('Order'),
                Tables\Columns\TextColumn::make('amount')->money('MYR'),
                Tables\Columns\TextColumn::make('knock_off_invoice_number')->label('Knock-off invoice')->placeholder('—'),
                Tables\Columns\TextColumn::make('bank_account')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => $state === 'completed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('autocount_sync_status')->label('AutoCount')->badge()->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'completed' => 'Completed']),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Complete refund')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (RefundNote $r) => ! $r->isCompleted())
                    ->form(fn (RefundNote $r) => [
                        Forms\Components\TextInput::make('bank_account')->label('Refund bank account')->default($r->bank_account)->required(),
                        Forms\Components\Textarea::make('remarks')->label('Payment remarks')->default($r->remarks)->required(),
                    ])
                    ->action(function (RefundNote $record, array $data) {
                        app(CreateRefundNote::class)->complete($record, auth()->user(), $data['bank_account'], $data['remarks']);
                        Notification::make()->title('Refund note completed — queued for AutoCount after EOD')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefundNotes::route('/'),
        ];
    }
}
