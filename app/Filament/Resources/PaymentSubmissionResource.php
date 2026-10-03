<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\ReviewPaymentSubmission;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Filament\Resources\PaymentSubmissionResource\Pages;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Section F: payment review queue with two-level approval for cash / counter payments. */
class PaymentSubmissionResource extends Resource
{
    protected static ?string $model = PaymentSubmission::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Payment Review';

    protected static ?int $navigationSort = 18;

    protected static ?string $modelLabel = 'payment submission';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('status', [PaymentSubmissionStatus::Submitted->value, PaymentSubmissionStatus::Verified->value])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Submission')->schema([
                Infolists\Components\TextEntry::make('quotation.number')->label('Order'),
                Infolists\Components\TextEntry::make('proformaInvoice.number')->label('Proforma'),
                Infolists\Components\TextEntry::make('customer.company_name')->label('Customer'),
                Infolists\Components\TextEntry::make('amount')->money('MYR'),
                Infolists\Components\TextEntry::make('method')->badge(),
                Infolists\Components\TextEntry::make('payment_date')->date('d/m/Y'),
                Infolists\Components\TextEntry::make('bank_account')->placeholder('—'),
                Infolists\Components\TextEntry::make('reference')->placeholder('—'),
                Infolists\Components\TextEntry::make('submitted_channel')->label('Channel'),
                Infolists\Components\TextEntry::make('submitter.name')->label('Submitted by')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('rejection_reason')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('remarks')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\Section::make('Approvals')->schema([
                Infolists\Components\TextEntry::make('level1Approver.name')->label('Level 1 (verified by)')->placeholder('—'),
                Infolists\Components\TextEntry::make('level1_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                Infolists\Components\TextEntry::make('level2Approver.name')->label('Level 2 (approved by)')->placeholder('—'),
                Infolists\Components\TextEntry::make('level2_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                Infolists\Components\TextEntry::make('payment.receipt.number')->label('Receipt')->placeholder('—'),
            ])->columns(5),
            Infolists\Components\Section::make('Evidence')->schema([
                Infolists\Components\ImageEntry::make('receipt_path')
                    ->label('Receipt image')
                    ->disk('public')
                    ->visible(fn (PaymentSubmission $record) => $record->receipt_path && preg_match('/\.(jpe?g|png|webp)$/i', $record->receipt_path)),
                Infolists\Components\TextEntry::make('receipt_path')
                    ->label('Receipt file')
                    ->formatStateUsing(fn ($state) => basename((string) $state))
                    ->url(fn (PaymentSubmission $record) => $record->receipt_path ? Storage::disk('public')->url($record->receipt_path) : null, shouldOpenInNewTab: true)
                    ->placeholder('No file uploaded'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('quotation.number')->label('Order')->searchable()->description(fn (PaymentSubmission $r) => $r->proformaInvoice?->number),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('MYR')->sortable(),
                Tables\Columns\TextColumn::make('method')->badge(),
                Tables\Columns\TextColumn::make('reference')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->description(fn (PaymentSubmission $r) => $r->requiresTwoApprovals() ? '2 approvals required' : null),
                Tables\Columns\TextColumn::make('level2Approver.name')->label('Approved by')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PaymentSubmissionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                    ->default(null),
                Tables\Filters\SelectFilter::make('method')->options(PaymentMethod::options()),
                Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'company_name')->searchable()->preload(),
                Tables\Filters\Filter::make('open')
                    ->label('Open only')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $q) => $q->whereIn('status', [PaymentSubmissionStatus::Submitted->value, PaymentSubmissionStatus::Verified->value])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Verify (L1)')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (PaymentSubmission $r) => $r->status === PaymentSubmissionStatus::Submitted && $r->requiresTwoApprovals())
                    ->requiresConfirmation()
                    ->modalDescription('Approval level 1: confirm the collection evidence is valid. A different user must then approve (level 2).')
                    ->action(function (PaymentSubmission $record) {
                        try {
                            app(ReviewPaymentSubmission::class)->verify($record, auth()->user());
                            Notification::make()->title('Payment verified — awaiting level 2 approval')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('approve')
                    ->label(fn (PaymentSubmission $r) => $r->requiresTwoApprovals() ? 'Approve (L2)' : 'Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PaymentSubmission $r) => $r->isOpen())
                    ->form([Forms\Components\Textarea::make('remarks')->label('Remarks (optional)')])
                    ->modalDescription(fn (PaymentSubmission $r) => 'Records the payment and receipt against '.$r->quotation?->number.'. A fully paid cash order proceeds to Cash Bill + CSN automatically.')
                    ->action(function (PaymentSubmission $record, array $data) {
                        try {
                            $result = app(ReviewPaymentSubmission::class)->approve($record, auth()->user(), $data['remarks'] ?? null);
                            $billing = $result['billing'];

                            Notification::make()
                                ->title('Payment approved — receipt '.$result['payment']->receipt?->number)
                                ->body($billing
                                    ? ($billing['ok']
                                        ? 'Cash Bill '.$billing['invoices']->pluck('number')->implode(', ').' issued · CSN '.$billing['csns']->pluck('number')->implode(', ')
                                        : 'Billing failed: '.$billing['error'])
                                    : null)
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PaymentSubmission $r) => $r->isOpen())
                    ->form([Forms\Components\Textarea::make('reason')->label('Rejection reason (sent to the customer)')->required()])
                    ->action(function (PaymentSubmission $record, array $data) {
                        try {
                            app(ReviewPaymentSubmission::class)->reject($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Payment rejected — customer notified')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentSubmissions::route('/'),
            'view' => Pages\ViewPaymentSubmission::route('/{record}'),
        ];
    }
}
