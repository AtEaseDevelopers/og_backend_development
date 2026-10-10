<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\VerifyCodCollection;
use App\Domains\Billing\Models\Payment;
use App\Enums\PaymentMethod;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;
use App\Filament\Concerns\HasExcelColumnFilters;

/**
 * Every COD payment of a day (date filter, today by default): collections the driver recorded on delivery,
 * waiting for Admin to verify the amount handed over, and payments Admin recorded on a COD order (Order
 * details → Payment summary), which are approved already. Verifying the last collection of a fully paid
 * order issues its COD invoice.
 */
class CodReconciliation extends Page implements HasTable
{
    use HasExcelColumnFilters, InteractsWithTable {
        HasExcelColumnFilters::filterTableQuery insteadof InteractsWithTable;
        InteractsWithTable::filterTableQuery as filamentFilterTableQuery;
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'COD Reconciliation';

    protected static ?int $navigationSort = 23;

    protected static string $view = 'filament.pages.cod-reconciliation';

    public function getTitle(): string
    {
        return 'COD Reconciliation';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Payment::query()
                ->with(['quotation.portalEnquiry', 'consignmentNote', 'customer', 'driver', 'approver', 'receiver', 'submission', 'invoice'])
                ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                ->where('status', '!=', 'cancelled')
                // driver COD collections, and any payment recorded on a COD order
                ->where(fn ($q) => $q->where('method', 'cod')->orWhereHas('quotation', fn ($o) => $o->where('order_type', 'cod'))))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Order / CSN')
                    ->state(fn (Payment $p) => $p->quotation?->orderNumber() ?? '—')
                    ->description(fn (Payment $p) => $p->consignmentNote?->number)
                    ->url(fn (Payment $p) => $p->quotation_id ? OrderDetail::urlFor('order', $p->quotation_id) : null),
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Recorded by')
                    ->state(fn (Payment $p) => $p->method === 'cod' ? 'Driver · '.($p->driver?->name ?? '—') : 'Admin · '.($p->receiver?->name ?? '—'))
                    ->description(fn (Payment $p) => PaymentMethod::tryFrom((string) $p->method)?->getLabel() ?? ($p->method === 'cod' ? 'Cash on delivery' : ucfirst((string) $p->method))),
                Tables\Columns\TextColumn::make('expected_amount')
                    ->label('Expected')
                    ->formatStateUsing(fn ($state) => filled($state) ? 'RM '.number_format((float) $state, 2) : null)
                    ->alignEnd()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => filled($state) ? 'RM '.number_format((float) $state, 2) : null)
                    ->alignEnd()
                    ->sortable()
                    ->description(fn (Payment $p) => (float) $p->shortage_amount > 0 ? 'Short RM '.number_format((float) $p->shortage_amount, 2) : null)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->formatStateUsing(fn ($state) => filled($state) ? 'RM '.number_format((float) $state, 2) : null)),
                Tables\Columns\TextColumn::make('verification')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Payment $p) => match (true) {
                        VerifyCodCollection::isPending($p) => 'Pending verification',
                        $p->method === 'cod' => 'Verified',
                        default => 'Approved by admin',
                    })
                    ->color(fn (string $state) => $state === 'Pending verification' ? 'warning' : 'success')
                    ->description(fn (Payment $p) => $p->approver ? $p->approver->name.' · '.$p->approved_at?->format('d/m H:i') : null),
                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('Invoice')
                    ->state(fn (Payment $p) => $p->invoice?->number ?? $p->quotation?->invoices()->where('type', 'cod')->where('status', '!=', 'cancelled')->value('number'))
                    ->placeholder('Not issued'),
            ])
            ->filters([
                // search sits in the filter row, beside the date
                Tables\Filters\Filter::make('search')
                    ->form([
                        Forms\Components\TextInput::make('q')
                            ->label('Search')
                            ->placeholder('Order, CSN or customer')
                            ->prefixIcon('heroicon-m-magnifying-glass')
                            ->live(debounce: 500),
                    ])
                    ->query(function (Builder $q, array $data): void {
                        $search = trim((string) ($data['q'] ?? ''));

                        if ($search === '') {
                            return;
                        }

                        $q->where(fn ($w) => $w
                            ->whereHas('quotation', fn ($o) => $o->where('number', 'like', "%{$search}%")->orWhereHas('portalEnquiry', fn ($e) => $e->where('order_number', 'like', "%{$search}%")))
                            ->orWhereHas('consignmentNote', fn ($c) => $c->where('number', 'like', "%{$search}%"))
                            ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', "%{$search}%")));
                    })
                    ->indicateUsing(fn (array $data) => filled($data['q'] ?? null) ? 'Search: '.$data['q'] : null),
                DateRangeFilter::make('created_at')
                    ->label('Payment date')
                    ->default(['from' => now()->toDateString(), 'until' => now()->toDateString()]),
                Tables\Filters\SelectFilter::make('state')
                    ->label('Status')
                    ->options(['pending' => 'Pending verification', 'verified' => 'Verified (driver)', 'admin' => 'Approved by admin'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'pending' => $q->where('method', 'cod')->where(fn ($w) => $w->whereNull('reconciliation_status')->orWhere('reconciliation_status', 'pending')),
                        'verified' => $q->where('method', 'cod')->where('reconciliation_status', 'reconciled'),
                        'admin' => $q->where('method', '!=', 'cod'),
                        default => $q,
                    }),
                Tables\Filters\SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Payment $p) => VerifyCodCollection::isPending($p))
                    ->modalHeading(fn (Payment $p) => 'Verify COD collection · '.($p->consignmentNote?->number ?? $p->quotation?->orderNumber()))
                    ->modalDescription(fn (Payment $p) => 'Driver '.($p->driver?->name ?? '—').' recorded RM '.number_format((float) $p->amount, 2).'. Enter the amount actually handed over; a shortage is recorded against the driver.')
                    ->modalSubmitActionLabel('Verify')
                    ->form([
                        Forms\Components\TextInput::make('received')
                            ->label('Amount received')
                            ->prefix('RM')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(fn (Payment $record) => number_format((float) $record->amount, 2, '.', '')),
                        Forms\Components\Textarea::make('remarks')->label('Remarks')->rows(2),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            $payment = app(VerifyCodCollection::class)->execute($record, (float) $data['received'], auth()->user(), $data['remarks'] ?? null);
                            $invoice = $payment->quotation?->invoices()->where('type', 'cod')->where('status', '!=', 'cancelled')->value('number');
                            Notification::make()
                                ->title('COD collection verified · RM '.number_format((float) $payment->amount, 2))
                                ->body($invoice ? 'COD invoice '.$invoice.' issued.' : null)
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('open')
                    ->label('Order')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Payment $p) => $p->quotation_id ? OrderDetail::urlFor('order', $p->quotation_id) : null)
                    ->visible(fn (Payment $p) => (bool) $p->quotation_id),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('verifySelected')
                    ->label('Verify selected (amounts as recorded)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Each selected driver collection is verified at the amount the driver recorded. Use Verify on a row to enter a different amount.')
                    ->action(function (Collection $records): void {
                        $done = 0;

                        foreach ($records as $record) {
                            if (VerifyCodCollection::isPending($record)) {
                                app(VerifyCodCollection::class)->execute($record, (float) $record->amount, auth()->user());
                                $done++;
                            }
                        }

                        Notification::make()->title($done.' COD collection(s) verified')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Payment $p) => VerifyCodCollection::isPending($p))
            ->emptyStateHeading('No COD payments on these dates')
            ->emptyStateDescription('Driver COD collections and payments recorded on COD orders appear here.');
    }
}
