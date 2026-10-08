<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Actions\AssignDeliveryOrderToLorry;
use App\Domains\Dispatch\Actions\CreateSubsheet;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ConsignmentNoteResource\Pages;
use App\Filament\Resources\ConsignmentNoteResource\RelationManagers;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use App\Filament\Pages\OrderDetail;
use App\Support\CurrentCompany;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConsignmentNoteResource extends Resource
{
    protected static ?string $model = ConsignmentNote::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Consignment Notes';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return ConsignmentNoteForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Every column sorts. Filtering is only in the filter card above the table
                // (ListConsignmentNotes::applyFilterBar), so no column is searchable and there is no table search box.
                Tables\Columns\TextColumn::make('number')
                    ->sortable()
                    ->description(fn (ConsignmentNote $record) => static::numberColumnNotes($record))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('CSN date')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->sortable()
                    ->description(fn (ConsignmentNote $record) => $record->salesperson?->name)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quotation.number')
                    ->label('Order')
                    ->url(fn (ConsignmentNote $record) => $record->quotation ? OrderDetail::urlFor('order', (int) $record->quotation_id) : null)
                    ->color('primary')
                    ->placeholder('—')
                    ->description(fn (ConsignmentNote $record) => $record->invoice_number)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order_type')
                    ->label('Order type')
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('claimer.name')
                    ->label('Claimed by')
                    ->description(fn (ConsignmentNote $record) => $record->claimed_at?->format('d/m H:i'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('billing_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof CsnBillingType ? $state->label() : $state)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                // delivery progress of the latest main DO, including a failed delivery and its reason
                Tables\Columns\TextColumn::make('delivery_status')
                    ->label('Delivery')
                    ->badge()
                    ->state(fn (ConsignmentNote $record) => static::deliveryState($record)['label'])
                    ->color(fn (ConsignmentNote $record) => static::deliveryState($record)['color'])
                    ->description(fn (ConsignmentNote $record) => static::deliveryState($record)['note'])
                    // a failed delivery: the full reason and the driver's remarks on hover
                    ->tooltip(fn (ConsignmentNote $record) => static::deliveryState($record)['tooltip'] ?? null)
                    ->toggleable(),
                // is the original CSN back from the driver (scanned with "Scan returned CSN")
                Tables\Columns\TextColumn::make('return_status')
                    ->label('CSN returned')
                    ->badge()
                    ->state(fn (ConsignmentNote $record) => match (true) {
                        $record->return_status === 'returned' || (bool) $record->returnedCsn => 'Returned',
                        $record->return_status === 'missing' => 'Missing',
                        $record->return_status === 'pending_return' => 'Not yet',
                        default => '—',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Returned' => 'success',
                        'Missing' => 'danger',
                        'Not yet' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (ConsignmentNote $record) => $record->returnedCsn
                        ? trim(($record->returnedCsn->returned_at?->format('d/m H:i') ?? '').($record->returnedCsn->receivedBy ? ' · '.$record->returnedCsn->receivedBy->name : ''), ' ·')
                        : null)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('MYR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('delivery_orders_count')
                    ->counts('deliveryOrders')
                    ->label('DO')
                    ->formatStateUsing(fn ($state) => (string) ($state ?? 0))
                    ->badge()
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'primary' : 'gray')
                    ->tooltip('View delivery orders')
                    ->sortable()
                    ->toggleable()
                    ->action(
                        Tables\Actions\Action::make('manageDeliveryOrders')
                            ->modalHeading(fn (ConsignmentNote $record) => 'Delivery orders — '.$record->number)
                            ->modalDescription(fn (ConsignmentNote $record) => $record->deliveryOrders()->count() === 0
                                ? 'No delivery orders yet. Assign a lorry to create the first DO.'
                                : 'Select a DO, then assign or change the lorry.')
                            ->modalWidth(MaxWidth::TwoExtraLarge)
                            ->form(fn (ConsignmentNote $record) => static::deliveryOrdersModalForm($record))
                            ->action(fn (ConsignmentNote $record, array $data) => static::handleDeliveryOrderModalSubmit($record, $data))
                            ->modalSubmitActionLabel(fn (ConsignmentNote $record) => $record->deliveryOrders()->count() === 0
                                ? 'Create DO & assign'
                                : 'Assign lorry')
                            ->modalSubmitAction(fn ($action, ConsignmentNote $record) => $record->status === CsnStatus::Cancelled
                                ? false
                                : $action)
                    ),
                Tables\Columns\TextColumn::make('deliveryOrder.lorry.registration_no')
                    ->label('Main lorry')
                    // the main DO's lorry, first DO if a CSN ever has two (a plain relationship sort would fail then)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        DB::table('delivery_orders')
                            ->join('lorries', 'lorries.id', '=', 'delivery_orders.lorry_id')
                            ->whereColumn('delivery_orders.consignment_note_id', 'consignment_notes.id')
                            ->whereNull('delivery_orders.parent_do_id')
                            ->orderBy('delivery_orders.id')
                            ->limit(1)
                            ->select('lorries.registration_no'),
                        $direction,
                    ))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subsheets_count')
                    ->counts('subsheets')
                    ->label('Subsheets')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            // No Filament filters: the CSN list page has its own Orders-style filter card
            // (ListConsignmentNotes::applyFilterBar), with a "Filters +" panel instead of a dropdown modal.
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkAssignLorry')
                    ->label('Assign to lorry')
                    ->icon('heroicon-o-truck')
                    ->modalHeading('Assign the selected CSNs to a lorry')
                    ->modalDescription('CSNs that already have a lorry, are cancelled or cannot be dispatched yet are skipped.')
                    ->modalSubmitActionLabel('Assign')
                    ->form(fn () => static::assignLorryFormSchema())
                    ->action(function (Collection $records, array $data): void {
                        [$done, $skipped] = [0, []];

                        foreach ($records as $record) {
                            if ($record->deliveryOrder()->exists() || $record->status === CsnStatus::Cancelled || ! $record->canAssignToLorry()) {
                                $skipped[] = $record->number;

                                continue;
                            }

                            try {
                                static::runAssignAndSubsheets($record, $data, notify: false);
                                $done++;
                            } catch (Throwable $e) {
                                $skipped[] = $record->number.' ('.$e->getMessage().')';
                            }
                        }

                        $notice = Notification::make()
                            ->title($done.' CSN(s) assigned')
                            ->body($skipped ? 'Skipped: '.implode(', ', $skipped) : null);
                        ($skipped ? $notice->warning() : $notice->success())->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('bulkSubsheets')
                    ->label('Create subsheets')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->modalHeading('Create subsheets for the selected CSNs')
                    ->modalDescription('Each selected CSN gets a subsheet for every lorry chosen. CSNs without a main lorry yet are skipped.')
                    ->modalSubmitActionLabel('Create subsheets')
                    // 1. subsheet or transfer · 2. transfer code · 3. lorries
                    ->form(fn () => [
                        Forms\Components\Radio::make('task_type')
                            ->label('Type')
                            ->options([
                                'incoming_psi' => 'Subsheet (pickup, bring goods to hub)',
                                'transfer' => 'Transfer (handover leg)',
                            ])
                            ->default('incoming_psi')
                            ->inline()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('transfer_code', null))
                            ->required(),
                        Forms\Components\Select::make('transfer_code')
                            ->label('Transfer code')
                            ->options(fn (Forms\Get $get) => TransferCode::query()
                                ->where('is_active', true)
                                ->when($get('task_type') === 'incoming_psi', fn ($q) => $q->where('type', 'incoming'))
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (TransferCode $t) => [$t->code => filled($t->name) ? $t->code.' — '.$t->name : $t->code]))
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('sub_lorry_ids')
                            ->label('Lorries')
                            ->helperText('Each selected CSN gets one subsheet per lorry.')
                            ->options(fn () => static::lorryOptions())
                            ->multiple()
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('segment_route')->label('Route')->maxLength(120),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        [$created, $skipped] = [0, []];

                        foreach ($records as $record) {
                            if (! $record->deliveryOrder?->job_sheet_id || $record->status === CsnStatus::Cancelled) {
                                $skipped[] = $record->number;

                                continue;
                            }

                            try {
                                // never a subsheet for the CSN's own main lorry
                                $lorries = collect($data['sub_lorry_ids'] ?? [])->reject(fn ($id) => (int) $id === (int) $record->deliveryOrder->lorry_id);
                                $created += static::createSubsheetsForLorries($record, $lorries, static::additionalTaskPayload($data));
                            } catch (Throwable $e) {
                                $skipped[] = $record->number.' ('.$e->getMessage().')';
                            }
                        }

                        $notice = Notification::make()
                            ->title($created.' subsheet(s) created')
                            ->body($skipped ? 'Skipped (no main lorry yet or cancelled): '.implode(', ', $skipped) : null);
                        ($skipped ? $notice->warning() : $notice->success())->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('previewPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (ConsignmentNote $record): string => static::pdfUrl($record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('claim')
                    ->label('Claim')
                    ->icon('heroicon-o-qr-code')
                    ->color('warning')
                    ->visible(fn (ConsignmentNote $record) => $record->isClaimable() && ! $record->transfer_claim_pending)
                    ->form([
                        Forms\Components\TextInput::make('qr_token')
                            ->label('Scan / paste CSN QR code (optional)')
                            ->helperText('Leave blank to claim the selected CSN directly.'),
                    ])
                    ->modalDescription(fn (ConsignmentNote $record) => 'Claiming '.$record->number.' reserves it for your branch / store and removes it from the unassigned queue.')
                    ->action(function (ConsignmentNote $record, array $data) {
                        try {
                            $claim = app(\App\Domains\Dispatch\Actions\ClaimCsn::class);
                            $csn = filled($data['qr_token'] ?? null)
                                ? $claim->executeByQrToken($data['qr_token'], auth()->user())
                                : $claim->execute($record, auth()->user());

                            Notification::make()->title('CSN '.$csn->number.' claimed by '.auth()->user()->name)->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('transferLorry')
                    ->label('Transfer lorry')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->visible(fn (ConsignmentNote $record) => \App\Domains\Dispatch\Actions\TransferJobSheetTask::canTransfer(auth()->user())
                        && $record->deliveryOrder()->exists()
                        && ! in_array($record->status, [CsnStatus::Delivered, CsnStatus::Cancelled], true))
                    ->form([
                        Forms\Components\Select::make('lorry_id')
                            ->label('New lorry')
                            ->options(fn () => static::lorryOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('driver_id')
                            ->label('New driver (optional, defaults to the lorry\'s driver)')
                            ->options(fn () => static::driverOptions())
                            ->searchable(),
                        Forms\Components\DateTimePicker::make('handover_at')->label('Handover time')->default(now())->seconds(false),
                        Forms\Components\TextInput::make('handover_location')->label('Handover location'),
                        Forms\Components\Textarea::make('reason')->required()
                            ->helperText('Allowed even while in route. Original and new lorry / driver, time and reason are recorded; drivers and the customer are notified; the new driver scans the CSN to claim it.'),
                    ])
                    ->action(function (ConsignmentNote $record, array $data) {
                        try {
                            $do = $record->deliveryOrder()->firstOrFail();
                            $transfer = app(\App\Domains\Dispatch\Actions\TransferJobSheetTask::class)->transferToLorry(
                                $do,
                                Lorry::query()->findOrFail($data['lorry_id']),
                                auth()->user(),
                                $data['reason'],
                                null,
                                isset($data['driver_id']) ? (int) $data['driver_id'] : null,
                                $data['handover_at'] ?? null,
                                $data['handover_location'] ?? null,
                            );

                            Notification::make()
                                ->title('Transferred to '.$transfer->toLorry?->registration_no.' / '.($transfer->toDriver?->name ?? 'no driver'))
                                ->body('Job sheet '.$transfer->toJobSheet?->number.'. Both drivers notified.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('collectPayment')
                    ->label('Collect Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (ConsignmentNote $record) => $record->billing_type === CsnBillingType::CashBill
                        && $record->payment_status !== PaymentStatus::Paid
                        && $record->status !== CsnStatus::Cancelled)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn (ConsignmentNote $record) => $record->total_amount),
                        Forms\Components\Select::make('method')
                            ->options([
                                'cash' => 'Cash',
                                'ewallet' => 'eWallet',
                                'bank_transfer' => 'Bank Transfer',
                                'online' => 'Online Payment',
                                'counter' => 'Pay at Counter',
                            ])
                            ->default('cash')
                            ->required(),
                        Forms\Components\TextInput::make('reference'),
                    ])
                    ->action(function (ConsignmentNote $record, array $data) {
                        $payment = app(RecordPayment::class)->execute([
                            'consignment_note_id' => $record->id,
                            'amount' => $data['amount'],
                            'method' => $data['method'],
                            'reference' => $data['reference'] ?? null,
                        ], auth()->user());
                        Notification::make()
                            ->title('Payment recorded')
                            ->body('Receipt '.$payment->receipt?->number)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('generateProforma')
                    ->label('Proforma')
                    ->icon('heroicon-o-document')
                    ->visible(fn (ConsignmentNote $record) => in_array($record->billing_type, [
                        CsnBillingType::Cod, CsnBillingType::CashBill,
                    ], true) && ! $record->proformaInvoice)
                    ->action(function (ConsignmentNote $record) {
                        $proforma = app(GenerateProformaInvoice::class)->execute($record);
                        Notification::make()->title('Proforma '.$proforma->number)->success()->send();
                    }),
                Tables\Actions\Action::make('assignLorry')
                    ->label('Assign to Lorry')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (ConsignmentNote $record) => ! $record->deliveryOrder()->exists()
                        && $record->status !== CsnStatus::Cancelled
                        && $record->canAssignToLorry())
                    ->modalHeading('Assign to Lorry')
                    ->modalSubmitActionLabel('Submit')
                    ->modalCancelActionLabel('Cancel')
                    ->form(fn () => static::assignLorryFormSchema())
                    ->action(function (ConsignmentNote $record, array $data) {
                        try {
                            static::runAssignAndSubsheets($record, $data);
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('addSubsheets')
                    ->label('Add Subsheets')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->visible(fn (ConsignmentNote $record) => $record->deliveryOrder?->job_sheet_id
                        && $record->status !== CsnStatus::Cancelled)
                    ->form(fn (ConsignmentNote $record) => [
                        Forms\Components\Select::make('sub_lorry_ids')
                            ->label('Lorries for subsheets')
                            ->helperText('Select one or more assisting / transfer lorries.')
                            ->options(fn () => static::lorryOptions(
                                excludeIds: array_filter([(int) $record->deliveryOrder?->lorry_id])
                            ))
                            ->multiple()
                            ->required()
                            ->searchable(),
                        ...static::subsheetOptionFields(),
                    ])
                    ->action(function (ConsignmentNote $record, array $data) {
                        try {
                            $created = static::createSubsheetsForLorries(
                                $record,
                                collect($data['sub_lorry_ids'] ?? []),
                                static::additionalTaskPayload($data),
                            );

                            Notification::make()
                                ->title($created ? "{$created} subsheet(s) created" : 'No subsheets created')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    /**
     * Delivery status of a CSN from its latest main DO (subsheet legs excluded): not assigned, assigned,
     * in transit, delivered or failed (with the driver's reason).
     *
     * @return array{label: string, color: string, note: ?string, tooltip?: ?string}
     */
    public static function deliveryState(ConsignmentNote $record): array
    {
        $dos = $record->relationLoaded('deliveryOrders') ? $record->deliveryOrders : $record->deliveryOrders()->with('failedDelivery')->get();
        $main = $dos->whereNull('parent_do_id')->sortByDesc('id')->first();

        if (! $main) {
            return ['label' => 'Not assigned', 'color' => 'gray', 'note' => null];
        }

        $status = $main->status instanceof DeliveryOrderStatus ? $main->status : DeliveryOrderStatus::tryFrom((string) $main->status);

        return match ($status) {
            DeliveryOrderStatus::Failed => [
                'label' => 'Failed',
                'color' => 'danger',
                'note' => trim(($main->failedDelivery?->reason ? str((string) $main->failedDelivery->reason)->replace('_', ' ')->ucfirst()->limit(24) : 'Delivery failed')
                    .($main->failedDelivery?->failed_at ? ' · '.$main->failedDelivery->failed_at->format('d/m H:i') : '')),
                'tooltip' => $main->failedDelivery
                    ? trim('Reason: '.($main->failedDelivery->reason ?: '—').($main->failedDelivery->remarks ? "
Driver remarks: ".$main->failedDelivery->remarks : ''))
                    : null,
            ],
            DeliveryOrderStatus::Delivered => ['label' => 'Delivered', 'color' => 'success', 'note' => $main->delivered_at?->format('d/m H:i')],
            DeliveryOrderStatus::InTransit => ['label' => 'In transit', 'color' => 'info', 'note' => null],
            DeliveryOrderStatus::Assigned => ['label' => 'Assigned', 'color' => 'primary', 'note' => null],
            DeliveryOrderStatus::Transferred, DeliveryOrderStatus::Reassigned => ['label' => ucfirst((string) $status->value), 'color' => 'warning', 'note' => null],
            DeliveryOrderStatus::Cancelled => ['label' => 'DO cancelled', 'color' => 'gray', 'note' => null],
            default => ['label' => ucfirst(str_replace('_', ' ', (string) ($status?->value ?? $main->status))), 'color' => 'gray', 'note' => null],
        };
    }

    /** Under the CSN number: customer DO / SA prefix, the transfer code(s) and Subsheet / Break bulk tags. */
    public static function numberColumnNotes(ConsignmentNote $record): ?HtmlString
    {
        $parts = array_filter([
            $record->customer_do_number ? 'DO '.e($record->customer_do_number) : null,
            $record->sa_prefix ? e($record->sa_prefix) : null,
        ]);

        $codes = collect([$record->transferCode?->code])
            ->merge($record->relationLoaded('subsheets') ? $record->subsheets->pluck('transfer_code') : [])
            ->filter()
            ->unique()
            ->values();

        $tags = collect();

        if ($codes->isNotEmpty()) {
            $tags->push('<span class="ow-csn-tag ow-csn-tag-code" title="Transfer code">'.e($codes->implode(', ')).'</span>');
        }

        $subsheets = $record->relationLoaded('subsheets') ? $record->subsheets->count() : 0;

        if ($subsheets > 0) {
            $tags->push('<span class="ow-csn-tag">Subsheet'.($subsheets > 1 ? ' ×'.$subsheets : '').'</span>');
        }

        if (($record->break_bulks_count ?? 0) > 0) {
            $tags->push('<span class="ow-csn-tag ow-csn-tag-bb">Break bulk'.($record->break_bulks_count > 1 ? ' ×'.$record->break_bulks_count : '').'</span>');
        }

        if ($parts === [] && $tags->isEmpty()) {
            return null;
        }

        return new HtmlString(trim(implode(' · ', $parts).($tags->isNotEmpty() ? '<span class="ow-csn-tags">'.$tags->implode('').'</span>' : '')));
    }

    /**
     * @return array<int, string>
     */
    public static function customerOptions(): array
    {
        $query = Customer::query()
            ->where('status', 'active')
            ->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('company_name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function lorryOptions(array $excludeIds = []): array
    {
        $query = Lorry::query()->where('is_active', true)->orderBy('registration_no');

        if ($companyId = CurrentCompany::id()) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->pluck('registration_no', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function driverOptions(): array
    {
        $query = Driver::query()->where('is_active', true)->orderBy('name');

        if ($companyId = CurrentCompany::id()) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function assignLorryFormSchema(): array
    {
        return [
            Forms\Components\Group::make([
                Forms\Components\Select::make('lorry_id')
                    ->label('Main lorry')
                    ->placeholder('Select Main lorry')
                    ->options(fn () => static::lorryOptions())
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $lorry = Lorry::query()->find($state);
                        $set('driver_id', $lorry?->default_driver_id);
                    }),
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->placeholder('Select Driver')
                    ->options(fn () => static::driverOptions())
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('sub_lorry_ids')
                    ->label('Additional lorries (subsheets)')
                    ->placeholder('Select Additional lorries')
                    ->helperText('Optional. Each selected lorry creates a subsheet under this CSN.')
                    ->options(fn (Forms\Get $get) => static::lorryOptions(
                        excludeIds: array_filter([(int) $get('lorry_id')])
                    ))
                    ->multiple()
                    ->searchable()
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('operating_date')
                    ->label('Operating date')
                    ->default(now()),
                Forms\Components\Select::make('transfer_code')
                    ->label('Transfer code')
                    ->placeholder('Select Transfer code')
                    ->options(fn () => TransferCode::query()
                        ->where('is_active', true)
                        ->pluck('name', 'code'))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('task_type')
                    ->label('Task type')
                    ->options([
                        'incoming_psi' => 'Incoming pickup (bring goods to hub)',
                        'transfer' => 'Transfer / handover leg',
                    ])
                    ->default('incoming_psi')
                    ->required(),
                Forms\Components\TextInput::make('segment_route')
                    ->label('Pickup route')
                    ->placeholder('Enter Pickup route')
                    ->maxLength(120),
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Enter any additional notes...')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function additionalTaskFormFields(bool $dehydrated = false): array
    {
        return [
            Forms\Components\Toggle::make('needs_additional_task')
                ->label('Requires pickup before main delivery')
                ->helperText('Assign a lorry to collect goods and deliver them to the hub before the main CSN delivery.')
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                    if ($state && $get('from_location_id')) {
                        $set('additional_task_to_location_id', $get('from_location_id'));
                    }
                })
                ->dehydrated($dehydrated)
                ->columnSpanFull(),
            Forms\Components\Group::make([
                Forms\Components\Placeholder::make('main_route_hint')
                    ->label('Main CSN route')
                    ->content(function (Forms\Get $get): string {
                        $from = static::locationLabel($get('from_location_id'));
                        $to = static::locationLabel($get('to_location_id'));

                        if (! $from && ! $to) {
                            return 'Set the CSN From/To area first.';
                        }

                        return trim(($from ?: '?').' → '.($to ?: '?'));
                    })
                    ->columnSpanFull(),
                Forms\Components\Select::make('additional_task_from_location_id')
                    ->label('Pickup from')
                    ->helperText('Where the assisting lorry collects the goods.')
                    ->options(fn () => static::locationOptions())
                    ->searchable()
                    ->live()
                    ->dehydrated($dehydrated),
                Forms\Components\Select::make('additional_task_to_location_id')
                    ->label('Deliver to hub')
                    ->helperText('Where goods are handed over before the main leg — usually the CSN From area.')
                    ->options(fn () => static::locationOptions())
                    ->default(fn (Forms\Get $get) => $get('from_location_id'))
                    ->searchable()
                    ->dehydrated($dehydrated),
                Forms\Components\Select::make('sub_lorry_ids')
                    ->label('Assisting lorry')
                    ->helperText('Lorry assigned for the pickup leg. Subsheet is created after the main lorry is assigned.')
                    ->options(fn () => static::lorryOptions())
                    ->multiple()
                    ->searchable()
                    ->dehydrated($dehydrated)
                    ->columnSpanFull(),
                Forms\Components\Select::make('additional_task_type')
                    ->label('Task type')
                    ->options([
                        'incoming_psi' => 'Incoming pickup (bring goods to hub)',
                        'transfer' => 'Transfer / handover leg',
                    ])
                    ->default('incoming_psi')
                    ->required()
                    ->live()
                    ->dehydrated($dehydrated),
                Forms\Components\Select::make('transfer_code')
                    ->label('Transfer code')
                    ->options(function (Forms\Get $get) {
                        $query = TransferCode::query()->where('is_active', true);

                        if (($get('additional_task_type') ?? 'incoming_psi') === 'incoming_psi') {
                            $query->where('type', 'incoming');
                        }

                        return $query->pluck('name', 'code');
                    })
                    ->searchable()
                    ->nullable()
                    ->dehydrated($dehydrated),
                Forms\Components\TextInput::make('psi_amount')
                    ->label('PSI amount')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->dehydrated($dehydrated),
                Forms\Components\TextInput::make('pso_amount')
                    ->label('PSO amount')
                    ->numeric()
                    ->default(0)
                    ->prefix('RM')
                    ->dehydrated($dehydrated),
                Forms\Components\Textarea::make('additional_task_notes')
                    ->label('Task notes')
                    ->rows(2)
                    ->dehydrated($dehydrated)
                    ->columnSpanFull(),
            ])
                ->visible(fn (Forms\Get $get): bool => (bool) $get('needs_additional_task'))
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    /** @return array<string, mixed> */
    public static function additionalTaskPayload(array $data): array
    {
        $from = static::locationLabel($data['additional_task_from_location_id'] ?? null);
        $to = static::locationLabel($data['additional_task_to_location_id'] ?? null);
        $segmentRoute = $data['additional_task_segment_route'] ?? null;

        if (! $segmentRoute && $from && $to) {
            $segmentRoute = "{$from} → {$to}";
        }

        return [
            'needs_additional_task' => (bool) ($data['needs_additional_task'] ?? false),
            'transfer_code' => $data['transfer_code'] ?? null,
            'task_type' => $data['additional_task_type'] ?? $data['task_type'] ?? 'incoming_psi',
            'segment_route' => $segmentRoute,
            'psi_amount' => $data['psi_amount'] ?? 0,
            'pso_amount' => $data['pso_amount'] ?? 0,
            'notes' => $data['additional_task_notes'] ?? $data['notes'] ?? null,
        ];
    }

    /** @return array<int, string> */
    public static function locationOptions(): array
    {
        return Location::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Location $location) => [
                $location->id => trim($location->code.' — '.$location->name),
            ])
            ->all();
    }

    public static function locationLabel(?string $locationId): ?string
    {
        if (! $locationId) {
            return null;
        }

        $location = Location::query()->find($locationId);

        return $location ? strtoupper($location->name) : null;
    }

    public static function runAssignAndSubsheets(ConsignmentNote $record, array $data, bool $notify = true): void
    {
        $do = app(AssignCsnToLorry::class)->execute(
            $record,
            Lorry::findOrFail($data['lorry_id']),
            $data['operating_date'] ?? null,
            isset($data['driver_id']) ? (int) $data['driver_id'] : null,
        );

        $created = static::createSubsheetsForLorries(
            $record->fresh(['deliveryOrder']),
            collect($data['sub_lorry_ids'] ?? []),
            static::additionalTaskPayload($data)
        );

        if ($notify) {
            Notification::make()
                ->title('Assigned — DO '.$do->number)
                ->body($created ? "{$created} subsheet(s) created for additional lorries." : null)
                ->success()
                ->send();
        }
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function subsheetOptionFields(bool $includeAmounts = false): array
    {
        $fields = [
            Forms\Components\Select::make('transfer_code')
                ->label('Transfer code')
                ->options(fn () => TransferCode::query()
                    ->where('is_active', true)
                    ->pluck('name', 'code'))
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('task_type')
                ->label('Task type')
                ->options([
                    'incoming_psi' => 'Incoming pickup (bring goods to hub)',
                    'transfer' => 'Transfer / handover leg',
                ])
                ->default('incoming_psi')
                ->required(),
            Forms\Components\TextInput::make('segment_route')
                ->label('Pickup route')
                ->maxLength(120),
        ];

        if ($includeAmounts) {
            $fields[] = Forms\Components\TextInput::make('psi_amount')->numeric()->default(0)->prefix('RM');
            $fields[] = Forms\Components\TextInput::make('pso_amount')->numeric()->default(0)->prefix('RM');
        }

        $fields[] = Forms\Components\Textarea::make('notes')->rows(2);

        return $fields;
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function deliveryOrdersModalForm(ConsignmentNote $record): array
    {
        $hasDos = $record->deliveryOrders()->exists();
        $readOnly = $record->status === CsnStatus::Cancelled;

        $fields = [];

        if ($hasDos) {
            $fields[] = Forms\Components\Radio::make('delivery_order_id')
                ->label('Delivery orders')
                ->options(fn () => static::deliveryOrderRadioOptions($record))
                ->descriptions(fn () => static::deliveryOrderRadioDescriptions($record))
                ->required()
                ->live()
                ->disabled($readOnly)
                ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                    if (! $state) {
                        return;
                    }

                    $do = DeliveryOrder::query()->with('jobSheet')->find($state);
                    if (! $do) {
                        return;
                    }

                    $set('lorry_id', $do->lorry_id);
                    $set('driver_id', $do->driver_id);
                    $set('operating_date', $do->jobSheet?->operating_date ?? now());
                })
                ->columnSpanFull();
        }

        $fields[] = Forms\Components\Group::make(static::doAssignLorryFields())
            ->visible(fn (Forms\Get $get) => ! $hasDos || filled($get('delivery_order_id')))
            ->disabled($readOnly)
            ->columns(2);

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public static function deliveryOrderRadioOptions(ConsignmentNote $record): array
    {
        return static::deliveryOrdersForModal($record)
            ->mapWithKeys(fn (DeliveryOrder $do) => [
                (string) $do->id => $do->number,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function deliveryOrderRadioDescriptions(ConsignmentNote $record): array
    {
        return static::deliveryOrdersForModal($record)
            ->mapWithKeys(function (DeliveryOrder $do) {
                $type = $do->parent_do_id ? 'Subsheet' : 'Main';
                $lorry = $do->lorry?->registration_no ?? 'No lorry assigned';
                $driver = $do->driver?->name ?? 'No driver';
                $status = $do->status instanceof DeliveryOrderStatus
                    ? ucfirst(str_replace('_', ' ', $do->status->value))
                    : (string) $do->status;

                return [
                    (string) $do->id => "{$type} · {$lorry} · {$driver} · {$status}",
                ];
            })
            ->all();
    }

    /** @return Collection<int, DeliveryOrder> */
    public static function deliveryOrdersForModal(ConsignmentNote $record): Collection
    {
        return $record->deliveryOrders()
            ->with(['lorry', 'driver'])
            ->orderByRaw('parent_do_id is null desc')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function doAssignLorryFields(): array
    {
        return [
            Forms\Components\Select::make('lorry_id')
                ->label('Lorry')
                ->options(fn () => static::lorryOptions())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $lorry = Lorry::query()->find($state);
                    $set('driver_id', $lorry?->default_driver_id);
                }),
            Forms\Components\Select::make('driver_id')
                ->label('Driver')
                ->options(fn () => static::driverOptions())
                ->searchable()
                ->required(),
            Forms\Components\DatePicker::make('operating_date')->default(now()),
        ];
    }

    public static function handleDeliveryOrderModalSubmit(ConsignmentNote $record, array $data): void
    {
        try {
            if ($record->deliveryOrders()->exists()) {
                $do = DeliveryOrder::query()
                    ->where('consignment_note_id', $record->id)
                    ->findOrFail($data['delivery_order_id']);

                $do = app(AssignDeliveryOrderToLorry::class)->execute(
                    $do,
                    Lorry::findOrFail($data['lorry_id']),
                    $data['operating_date'] ?? null,
                    isset($data['driver_id']) ? (int) $data['driver_id'] : null,
                );

                Notification::make()
                    ->title('Lorry assigned — '.$do->number)
                    ->success()
                    ->send();

                return;
            }

            $do = app(AssignCsnToLorry::class)->execute(
                $record,
                Lorry::findOrFail($data['lorry_id']),
                $data['operating_date'] ?? null,
                isset($data['driver_id']) ? (int) $data['driver_id'] : null,
            );

            Notification::make()
                ->title('DO created — '.$do->number)
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function createSubsheetsForLorries(ConsignmentNote $record, Collection $lorryIds, array $data): int
    {
        $do = $record->deliveryOrder;
        if (! $do?->job_sheet_id) {
            throw new \InvalidArgumentException('Assign a main lorry first before creating subsheets.');
        }

        $lorries = Lorry::query()
            ->with('defaultDriver')
            ->whereIn('id', $lorryIds->filter()->unique()->all())
            ->get();

        $created = 0;
        $action = app(CreateSubsheet::class);

        foreach ($lorries as $lorry) {
            if ((int) $lorry->id === (int) $do->lorry_id) {
                continue;
            }

            $already = $record->subsheets()
                ->where('sub_lorry_id', $lorry->id)
                ->exists();

            if ($already) {
                continue;
            }

            $action->execute($do, array_merge(
                [
                    'sub_lorry_id' => $lorry->id,
                    'sub_driver_id' => $lorry->default_driver_id,
                ],
                static::additionalTaskPayload($data),
            ));
            $created++;
        }

        return $created;
    }

    public static function pdfUrl(ConsignmentNote $record): string
    {
        return route('filament.admin.consignment-notes.pdf', [
            'tenant' => Filament::getTenant(),
            'consignmentNote' => $record,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubsheetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsignmentNotes::route('/'),
            'create' => Pages\CreateConsignmentNote::route('/create'),
            'view' => Pages\ViewConsignmentNote::route('/{record}'),
            'edit' => Pages\EditConsignmentNote::route('/{record}/edit'),
        ];
    }
}
