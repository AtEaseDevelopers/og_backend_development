<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Actions\AssignDeliveryOrderToLorry;
use App\Domains\Dispatch\Actions\CreateSubsheet;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ConsignmentNoteResource\Pages;
use App\Filament\Resources\ConsignmentNoteResource\RelationManagers;
use App\Filament\Resources\ConsignmentNoteResource\Schemas\ConsignmentNoteForm;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class ConsignmentNoteResource extends Resource
{
    protected static ?string $model = ConsignmentNote::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Consignment Notes';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return ConsignmentNoteForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\TextColumn::make('billing_type')->badge()->formatStateUsing(
                    fn ($state) => $state instanceof CsnBillingType ? $state->label() : $state
                ),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->badge(),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('delivery_orders_count')
                    ->counts('deliveryOrders')
                    ->label('DO')
                    ->formatStateUsing(fn ($state) => (string) ($state ?? 0))
                    ->badge()
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'primary' : 'gray')
                    ->tooltip('View delivery orders')
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
                Tables\Columns\TextColumn::make('deliveryOrder.lorry.registration_no')->label('Main lorry'),
                Tables\Columns\TextColumn::make('subsheets_count')
                    ->counts('subsheets')
                    ->label('Subsheets'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->searchPlaceholder('Search CSN no. or customer…')
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(CsnStatus::cases())->mapWithKeys(
                        fn (CsnStatus $c) => [$c->value => $c->getLabel()]
                    )),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn (PaymentStatus $c) => [$c->value => $c->getLabel()]
                    )),
                Tables\Filters\SelectFilter::make('billing_type')
                    ->label('Billing type')
                    ->options(collect(CsnBillingType::cases())->mapWithKeys(
                        fn (CsnBillingType $c) => [$c->value => $c->label()]
                    )),
                Tables\Filters\SelectFilter::make('quotation_id')
                    ->label('Quotation')
                    ->relationship('quotation', 'number')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('main_lorry_id')
                    ->label('Main lorry')
                    ->form([
                        Forms\Components\Select::make('value')
                            ->label('Main lorry')
                            ->options(fn () => static::lorryOptions())
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $lorryId): Builder => $query->whereHas(
                                'deliveryOrder',
                                fn (Builder $query) => $query->where('lorry_id', $lorryId)
                            ),
                        );
                    }),
                Tables\Filters\TernaryFilter::make('assigned')
                    ->label('Assigned to lorry')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('deliveryOrder'),
                        false: fn (Builder $query) => $query->whereDoesntHave('deliveryOrder'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\TernaryFilter::make('has_subsheets')
                    ->label('Has subsheets')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('subsheets'),
                        false: fn (Builder $query) => $query->whereDoesntHave('subsheets'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\Filter::make('issued_at')
                    ->label('Issued date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('issued_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('issued_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('job_date')
                    ->label('Job date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('job_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('job_date', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->label('Created at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('total_amount')
                    ->label('Total amount')
                    ->form([
                        Forms\Components\TextInput::make('min')
                            ->label('Min (MYR)')
                            ->numeric(),
                        Forms\Components\TextInput::make('max')
                            ->label('Max (MYR)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min'] ?? null,
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max'] ?? null,
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
                            );
                    }),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
     * @return array<int, string>
     */
    public static function customerOptions(): array
    {
        $query = \App\Domains\MasterData\Models\Customer::query()
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
            Forms\Components\Select::make('lorry_id')
                ->label('Main lorry')
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
            Forms\Components\Select::make('sub_lorry_ids')
                ->label('Additional lorries (subsheets)')
                ->helperText('Optional. Each selected lorry creates a subsheet under this CSN.')
                ->options(fn (Forms\Get $get) => static::lorryOptions(
                    excludeIds: array_filter([(int) $get('lorry_id')])
                ))
                ->multiple()
                ->searchable(),
            Forms\Components\DatePicker::make('operating_date')->default(now()),
            ...static::subsheetOptionFields(),
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

    public static function runAssignAndSubsheets(ConsignmentNote $record, array $data): void
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

        Notification::make()
            ->title('Assigned — DO '.$do->number)
            ->body($created ? "{$created} subsheet(s) created for additional lorries." : null)
            ->success()
            ->send();
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function subsheetOptionFields(): array
    {
        return [
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
            Forms\Components\TextInput::make('psi_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\TextInput::make('pso_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\Textarea::make('notes')->rows(2),
        ];
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
                $status = $do->status instanceof \App\Enums\DeliveryOrderStatus
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
