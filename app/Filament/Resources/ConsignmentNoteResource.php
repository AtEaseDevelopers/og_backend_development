<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Actions\CreateSubsheet;
use App\Domains\MasterData\Models\Driver;
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
use Filament\Tables;
use Filament\Tables\Table;
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
                Tables\Columns\TextColumn::make('sourceBranch.name')->label('Branch'),
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\TextColumn::make('billing_type')->badge()->formatStateUsing(
                    fn ($state) => $state instanceof CsnBillingType ? $state->label() : $state
                ),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->badge(),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO'),
                Tables\Columns\TextColumn::make('deliveryOrder.lorry.registration_no')->label('Main lorry'),
                Tables\Columns\TextColumn::make('subsheets_count')
                    ->counts('subsheets')
                    ->label('Subsheets'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source_branch_id')->relationship('sourceBranch', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CsnStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => ucfirst(str_replace('_', ' ', $c->value))]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('collectPayment')
                    ->label('Collect Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (ConsignmentNote $record) => $record->billing_type === CsnBillingType::CashBill
                        && $record->payment_status !== PaymentStatus::Paid->value
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
                                $data
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
            $data
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
                ->options([
                    'transfer' => 'Transfer / multi-driver',
                    'incoming_psi' => 'Incoming PSI',
                ])
                ->default('transfer')
                ->required(),
            Forms\Components\TextInput::make('psi_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\TextInput::make('pso_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\Textarea::make('notes')->rows(2),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $lorryIds
     */
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

            $already = $do->subsheets()
                ->where('sub_lorry_id', $lorry->id)
                ->exists();

            if ($already) {
                continue;
            }

            $action->execute($do, [
                'sub_lorry_id' => $lorry->id,
                'sub_driver_id' => $lorry->default_driver_id,
                'transfer_code' => $data['transfer_code'] ?? null,
                'task_type' => $data['task_type'] ?? 'transfer',
                'psi_amount' => $data['psi_amount'] ?? 0,
                'pso_amount' => $data['pso_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);
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
