<?php

namespace App\Filament\Resources;

use App\Domains\Dispatch\Actions\TransferJobSheetTask;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use App\Filament\Resources\JobSheetResource\Pages;
use App\Filament\Resources\JobSheetResource\Pages\ListJobSheets;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Livewire;
use Throwable;

class JobSheetResource extends Resource
{
    protected static ?string $model = JobSheet::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Job Sheet Management';

    protected static ?string $modelLabel = 'Job Sheet';

    protected static ?string $pluralModelLabel = 'Job Sheets';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')
                ->relationship('driver', 'name')
                ->searchable()
                ->disabled(fn (?JobSheet $record) => $record?->status === JobSheetStatus::InTransit
                    || $record?->status === JobSheetStatus::Completed),
            Forms\Components\Select::make('lorry_id')
                ->relationship('lorry', 'registration_no')
                ->searchable()
                ->disabled(fn (?JobSheet $record) => $record?->status !== JobSheetStatus::Draft),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('operatingBranch.name')->label('Operating Branch'),
                Infolists\Components\TextEntry::make('lorry.registration_no')->label('Lorry'),
                Infolists\Components\TextEntry::make('driver.name')->label('Driver'),
                Infolists\Components\TextEntry::make('operating_date')->date(),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\IconEntry::make('is_shared_dispatch')->boolean()->label('Shared dispatch'),
                Infolists\Components\TextEntry::make('checked_in_at')->dateTime(),
            ])->columns(2),
            Infolists\Components\RepeatableEntry::make('deliveryOrders')
                ->label('Tasks')
                ->schema([
                    Infolists\Components\TextEntry::make('number')->label('DO'),
                    Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('consignmentNote.delivery_address')->label('Address'),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Job Sheet Number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operating_date')
                    ->label('Operating Date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operatingBranch.name')
                    ->label('Operating Branch'),
                Tables\Columns\TextColumn::make('lorry.registration_no')
                    ->label('Lorry'),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver'),
                Tables\Columns\TextColumn::make('deliveryOrders_count')
                    ->counts('deliveryOrders')
                    ->label('Task Count'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDetails')
                    ->label('View Details')
                    ->visible(fn (): bool => ! Livewire::current() instanceof ListJobSheets)
                    ->action(function (JobSheet $record): void {
                        $livewire = Livewire::current();

                        if ($livewire instanceof ListJobSheets) {
                            $livewire->selectJobSheet($record->id);

                            return;
                        }

                        redirect(static::getUrl('view', ['record' => $record]));
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (JobSheet $record) => $record->status === JobSheetStatus::Draft),
                Tables\Actions\Action::make('viewLog')
                    ->label('View Log')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (JobSheet $record): string => static::getUrl('audit-log', ['record' => $record])),
                Tables\Actions\Action::make('transfer_task')
                    ->label('Transfer task')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (JobSheet $record) => in_array($record->status, [
                        JobSheetStatus::Draft,
                        JobSheetStatus::InTransit,
                    ], true))
                    ->form([
                        Forms\Components\Select::make('delivery_order_id')
                            ->label('Delivery Order on this sheet')
                            ->options(fn (JobSheet $record) => $record->deliveryOrders()
                                ->whereNotIn('status', [
                                    DeliveryOrderStatus::Delivered->value,
                                    DeliveryOrderStatus::Cancelled->value,
                                ])
                                ->get()
                                ->mapWithKeys(fn (DeliveryOrder $do) => [
                                    $do->id => $do->number.' — '.$do->status?->value,
                                ]))
                            ->required(),
                        Forms\Components\Select::make('mode')
                            ->options([
                                'job_sheet' => 'Existing Job Sheet',
                                'lorry' => 'Another lorry (create/find Job Sheet)',
                            ])
                            ->default('lorry')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('to_job_sheet_id')
                            ->label('Target Job Sheet')
                            ->options(fn (JobSheet $record) => JobSheet::query()
                                ->where('id', '!=', $record->id)
                                ->whereDate('operating_date', $record->operating_date)
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (JobSheet $js) => [
                                    $js->id => $js->number.' — '.$js->lorry?->registration_no,
                                ]))
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('mode') === 'job_sheet')
                            ->required(fn (Forms\Get $get) => $get('mode') === 'job_sheet'),
                        Forms\Components\Select::make('lorry_id')
                            ->label('Target lorry')
                            ->options(fn () => Lorry::query()
                                ->with('branch')
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Lorry $lorry) => [
                                    $lorry->id => $lorry->registration_no.' ['.$lorry->branch?->code.']',
                                ]))
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('mode') === 'lorry')
                            ->required(fn (Forms\Get $get) => $get('mode') === 'lorry'),
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->helperText('Required for in-transit controlled transfers.'),
                    ])
                    ->action(function (JobSheet $record, array $data) {
                        try {
                            $do = DeliveryOrder::query()->findOrFail($data['delivery_order_id']);
                            $action = app(TransferJobSheetTask::class);

                            if (($data['mode'] ?? 'lorry') === 'job_sheet') {
                                $transfer = $action->execute(
                                    $do,
                                    JobSheet::findOrFail($data['to_job_sheet_id']),
                                    auth()->user(),
                                    $data['reason']
                                );
                            } else {
                                $transfer = $action->transferToLorry(
                                    $do,
                                    Lorry::findOrFail($data['lorry_id']),
                                    auth()->user(),
                                    $data['reason']
                                );
                            }

                            Notification::make()
                                ->title('Task transferred')
                                ->body($do->number.' → '.$transfer->toJobSheet?->number)
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobSheets::route('/'),
            'view' => Pages\ViewJobSheet::route('/{record}'),
            'audit-log' => Pages\ViewJobSheetAuditLog::route('/{record}/audit-log'),
            'edit' => Pages\EditJobSheet::route('/{record}/edit'),
        ];
    }
}
