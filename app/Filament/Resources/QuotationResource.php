<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Notification\Models\NotificationLog;
use App\Domains\Quotation\Actions\AcceptQuotation;
use App\Domains\Quotation\Actions\ChangeOrderType;
use App\Domains\Quotation\Actions\ClosePendingCustomerReviews;
use App\Domains\Quotation\Actions\RejectQuotation;
use App\Domains\Quotation\Actions\ReleaseOrder;
use App\Domains\Quotation\Actions\ReviseQuotation;
use App\Domains\Quotation\Actions\SendQuotation;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\QuotationRejectionCategory;
use App\Enums\QuotationStatus;
use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Schemas\QuotationForm;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Quotation Management';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'quotation';

    protected static ?string $pluralModelLabel = 'quotations';

    public static function form(Form $form): Form
    {
        return QuotationForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Quote ID')
                    ->description(fn (Quotation $record) => 'v'.$record->version.($record->customer_do_number ? ' · DO '.$record->customer_do_number : ''))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order_type')
                    ->label('Order type')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stage')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('billing_status')
                    ->label('Payment / billing')
                    ->badge()
                    ->description(fn (Quotation $record) => (float) $record->paid_amount > 0
                        ? 'Paid RM '.number_format((float) $record->paid_amount, 2)
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('salesperson.name')
                    ->label('Salesperson')
                    ->description(fn (Quotation $record) => $record->saLocation?->code)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pricing_source')
                    ->label('Pricing')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount (MYR)')
                    ->money('MYR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->searchPlaceholder('Search ID, customer or DO number')
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('salesperson_id')
                    ->label('Salesperson')
                    ->relationship('salesperson', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('sa_location_id')
                    ->label('SA location')
                    ->options(fn () => SaLocation::query()->orderBy('code')->get()->mapWithKeys(fn (SaLocation $l) => [$l->id => $l->code.' — '.$l->name])),
                Tables\Filters\SelectFilter::make('order_type')
                    ->label('Order type')
                    ->options(OrderType::options()),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment method')
                    ->options(PaymentMethod::options()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Order status')
                    ->multiple()
                    ->options(collect(QuotationStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->label()]
                    )),
                Tables\Filters\SelectFilter::make('billing_status')
                    ->label('Payment / billing')
                    ->options(collect(BillingStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),
                Tables\Filters\Filter::make('pending_review')
                    ->label('Pending customer review queue')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        QuotationStatus::Sent->value, QuotationStatus::PendingReview->value, QuotationStatus::Negotiation->value,
                    ])),
                Tables\Filters\Filter::make('latest_only')
                    ->label('Latest versions only')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query) => $query->where('status', '!=', QuotationStatus::Superseded->value)),
                Tables\Filters\SelectFilter::make('pricing_source')
                    ->label('Pricing source')
                    ->options([
                        'default' => 'Default Pricing',
                        'special' => 'Customer Special',
                        'previous' => 'Previous Quotation',
                        'formula' => 'Formula Pricing',
                        'manual' => 'Manual Pricing',
                        'ocr' => 'OCR',
                        'portal' => 'Portal Enquiry',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('Created at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('total_amount')
                    ->label('Total amount')
                    ->form([
                        Forms\Components\TextInput::make('min')->label('Min (MYR)')->numeric(),
                        Forms\Components\TextInput::make('max')->label('Max (MYR)')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'] ?? null, fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount))
                            ->when($data['max'] ?? null, fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount));
                    }),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->icon('heroicon-o-funnel')
                    ->iconButton()
                    ->label('')
                    ->color('gray')
            )
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Quotation $record) => $record->status->isEditable() || auth()->user()?->isSuperadmin()),
                Tables\Actions\Action::make('previewPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Quotation $record): string => route('filament.admin.quotations.pdf', [
                        'tenant' => \Filament\Facades\Filament::getTenant(),
                        'quotation' => $record,
                    ]))
                    ->openUrlInNewTab(),
                Tables\Actions\ActionGroup::make(static::stageActions())
                    ->label('Order actions')
                    ->icon('heroicon-o-bolt')
                    ->button()
                    ->color('primary'),
            ]);
    }

    /**
     * Stage actions shared by the table and the view page (sections C–G).
     *
     * @return list<Tables\Actions\Action>
     */
    public static function stageActions(): array
    {
        return [
            Tables\Actions\Action::make('send')
                ->label(fn (Quotation $record) => $record->version > 1 ? 'Send revised quotation' : 'Send quotation')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (Quotation $record) => in_array($record->status, [
                    QuotationStatus::Draft, QuotationStatus::Negotiation, QuotationStatus::Sent, QuotationStatus::PendingReview,
                ], true) && $record->isLatestVersion())
                ->form([
                    Forms\Components\CheckboxList::make('channels')
                        ->options([
                            NotificationLog::CHANNEL_EMAIL => 'Email (portal link)',
                            NotificationLog::CHANNEL_WHATSAPP => 'WhatsApp (share link)',
                        ])
                        ->default([NotificationLog::CHANNEL_EMAIL, NotificationLog::CHANNEL_WHATSAPP])
                        ->required(),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $result = app(SendQuotation::class)->execute($record, auth()->user(), $data['channels']);
                        $wa = $record->notificationLogs()->where('channel', 'whatsapp')->latest('id')->first();

                        Notification::make()
                            ->title($result->status === QuotationStatus::Confirmed || $result->status === QuotationStatus::Accepted
                                ? 'Quotation sent and auto-accepted (consent letter on file)'
                                : 'Quotation sent to customer')
                            ->body($wa?->whatsapp_url ? 'WhatsApp message ready — open it from the Notifications log.' : null)
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('acceptOnBehalf')
                ->label('Record customer acceptance')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Quotation $record) => ($record->status->isCustomerActionable() || $record->status === QuotationStatus::Draft) && $record->isLatestVersion())
                ->form([
                    Forms\Components\Select::make('channel')
                        ->label('Confirmation channel')
                        ->options([
                            AcceptQuotation::CHANNEL_WHATSAPP => 'WhatsApp',
                            AcceptQuotation::CHANNEL_EMAIL => 'Email',
                            AcceptQuotation::CHANNEL_ADMIN => 'Counter / phone (admin recorded)',
                        ])
                        ->default(AcceptQuotation::CHANNEL_WHATSAPP)
                        ->required(),
                    Forms\Components\TextInput::make('confirmed_by_name')
                        ->label('Confirmed by (customer contact)')
                        ->required(),
                    Forms\Components\Textarea::make('consent_evidence')
                        ->label('Evidence / remarks')
                        ->placeholder('e.g. WhatsApp confirmation received 1 Oct 10:15 from +60 12-345 6789'),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $result = app(AcceptQuotation::class)->execute($record, $data['channel'], $data['confirmed_by_name'], auth()->user(), $data['consent_evidence'] ?? null);
                        Notification::make()
                            ->title('Customer confirmation recorded')
                            ->body('Status: '.$result->status->getLabel().'. Proforma '.$result->proformaInvoice?->number.' generated.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('rejectOrNegotiate')
                ->label('Record rejection / negotiation')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->visible(fn (Quotation $record) => $record->status->isCustomerActionable() && $record->isLatestVersion())
                ->form([
                    Forms\Components\Select::make('category')
                        ->label('Outcome')
                        ->options(QuotationRejectionCategory::options())
                        ->required(),
                    Forms\Components\Textarea::make('reason')->label('Details'),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $result = app(RejectQuotation::class)->execute($record, QuotationRejectionCategory::from($data['category']), $data['reason'] ?? null, auth()->user(), 'admin');
                        Notification::make()->title('Recorded: '.$result->status->getLabel())->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('revise')
                ->label('Revise (new version)')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (Quotation $record) => $record->isLatestVersion()
                    && ! in_array($record->status, [QuotationStatus::Converted, QuotationStatus::Superseded], true)
                    && (! $record->status->isConfirmedOrLater() || auth()->user()?->isSuperadmin()))
                ->form([
                    Forms\Components\Textarea::make('reason')->label('Reason for revision')->required(),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $revision = app(ReviseQuotation::class)->execute($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Version '.$revision->version.' created')->success()->send();

                        return redirect(static::getUrl('edit', ['record' => $revision]));
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('changeOrderType')
                ->label('Change order type')
                ->icon('heroicon-o-arrows-right-left')
                ->visible(fn (Quotation $record) => $record->status !== QuotationStatus::Converted && ! $record->status->isTerminal())
                ->form(fn (Quotation $record) => [
                    Forms\Components\Placeholder::make('current')
                        ->label('Current order type')
                        ->content($record->orderType()?->getLabel() ?? 'Not set'),
                    Forms\Components\Select::make('order_type')
                        ->label('New order type')
                        ->options(function () use ($record) {
                            $current = $record->orderType();
                            $allowed = $current ? $current->allowedTransitions() : OrderType::cases();

                            return collect($allowed)
                                ->reject(fn (OrderType $t) => $t === OrderType::Term && ! $record->customer?->is_credit)
                                ->mapWithKeys(fn (OrderType $t) => [$t->value => $t->getLabel()]);
                        })
                        ->required()
                        ->helperText('Term → Term / Cash / COD · COD → Cash only · Cash cannot change.'),
                    Forms\Components\Textarea::make('reason')->label('Reason'),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        app(ChangeOrderType::class)->execute($record, OrderType::from($data['order_type']), auth()->user(), $data['reason'] ?? null);
                        Notification::make()->title('Order type updated')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('release')
                ->label('Admin release')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Confirmed
                    && ! $record->isReleased()
                    && $record->billingStatus() !== BillingStatus::Generated
                    && (auth()->user()?->is_hq || auth()->user()?->hasAnyRole(['hq_admin', 'branch_manager', 'finance'])))
                ->form(fn (Quotation $record) => [
                    Forms\Components\Placeholder::make('summary')
                        ->label('Order')
                        ->content(sprintf('%s · %s · Total RM %s · Paid RM %s · Outstanding RM %s',
                            $record->number,
                            $record->orderType()?->getLabel() ?? '—',
                            number_format((float) $record->total_amount, 2),
                            number_format((float) $record->paid_amount, 2),
                            number_format($record->outstandingAmount(), 2))),
                    Forms\Components\Textarea::make('reason')
                        ->label('Release reason / collection plan')
                        ->required(),
                ])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $result = app(ReleaseOrder::class)->execute($record, auth()->user(), $data['reason']);
                        Notification::make()
                            ->title($result['ok'] ? 'Order released — billing issued and CSN created' : 'Order released, but billing failed')
                            ->body($result['error'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('blockCod')
                ->label('Block COD order')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (Quotation $record) => $record->orderType() === OrderType::Cod && ! $record->cod_blocked && $record->status !== QuotationStatus::Converted)
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(function (Quotation $record, array $data) {
                    try {
                        app(ReleaseOrder::class)->blockCod($record, auth()->user(), $data['reason']);
                        Notification::make()->title('COD order blocked')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('unblockCod')
                ->label('Unblock COD order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Quotation $record) => $record->orderType() === OrderType::Cod && $record->cod_blocked)
                ->form([Forms\Components\Textarea::make('reason')->label('Reason (optional)')])
                ->action(function (Quotation $record, array $data) {
                    try {
                        $result = app(ReleaseOrder::class)->unblockCod($record, auth()->user(), $data['reason'] ?? null);
                        Notification::make()
                            ->title($result['ok'] ? 'COD order unblocked' : 'Unblocked, but billing failed')
                            ->body($result['error'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('generateBilling')
                ->label(fn (Quotation $record) => $record->billingStatus() === BillingStatus::Failed ? 'Retry billing generation' : 'Generate Invoice / Cash Bill → CSN')
                ->icon('heroicon-o-receipt-percent')
                ->color(fn (Quotation $record) => $record->billingStatus() === BillingStatus::Failed ? 'danger' : 'primary')
                ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Confirmed && $record->billingStatus() !== BillingStatus::Generated)
                ->requiresConfirmation()
                ->modalDescription(fn (Quotation $record) => $record->billingBlockReason()
                    ?? ($record->billing_error ? 'Last error: '.$record->billing_error : 'Invoice / Cash Bill will be issued and the CSN created as Pending Lorry Assignment.'))
                ->action(function (Quotation $record) {
                    try {
                        $result = app(GenerateOrderBilling::class)->execute($record, auth()->user());
                        Notification::make()
                            ->title($result['ok']
                                ? 'Billing issued: '.$result['invoices']->pluck('number')->implode(', ').' · CSN: '.$result['csns']->pluck('number')->implode(', ')
                                : 'Billing generation failed')
                            ->body($result['error'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Tables\Actions\Action::make('reopen')
                ->label('Reopen closed case')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (Quotation $record) => $record->status === QuotationStatus::Closed)
                ->requiresConfirmation()
                ->action(function (Quotation $record) {
                    app(ClosePendingCustomerReviews::class)->reopen($record, auth()->user());
                    Notification::make()->title('Case reopened as draft')->success()->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> */
    public static function customerOptions(): array
    {
        $query = Customer::query()
            ->where('status', 'active')
            ->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Customer $customer) => [
                (string) $customer->id => trim(($customer->code ? $customer->code.' — ' : '').$customer->company_name),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public static function saLocationOptions(): array
    {
        return SaLocation::query()
            ->where('is_active', true)
            ->when(CurrentCompany::branchId(), fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (SaLocation $l) => [(string) $l->id => $l->code.' — '.$l->name.' ('.$l->csn_prefix.')'])
            ->all();
    }
}
