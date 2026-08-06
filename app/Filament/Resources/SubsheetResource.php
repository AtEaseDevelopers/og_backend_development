<?php

namespace App\Filament\Resources;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\TransferCode;
use App\Filament\Resources\SubsheetResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubsheetResource extends Resource
{
    protected static ?string $model = Subsheet::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Subsheets';

    protected static ?int $navigationSort = 52;

    /** Create subsheets from Consignment Notes instead of this standalone menu. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('delivery_order_id')
                ->label('Delivery Order')
                ->options(fn () => DeliveryOrder::query()
                    ->whereNotNull('job_sheet_id')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get()
                    ->mapWithKeys(fn (DeliveryOrder $do) => [
                        $do->id => $do->number.' — '.$do->status?->value,
                    ]))
                ->searchable()
                ->required()
                ->live(),
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
            Forms\Components\Select::make('sub_driver_id')
                ->relationship('subDriver', 'name')
                ->searchable()
                ->nullable()
                ->label('Assisting / sub driver'),
            Forms\Components\Select::make('sub_lorry_id')
                ->relationship('subLorry', 'registration_no')
                ->searchable()
                ->nullable()
                ->label('Sub lorry'),
            Forms\Components\Select::make('subcontractor_id')
                ->relationship('subcontractor', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('segment_route')->maxLength(120),
            Forms\Components\TextInput::make('psi_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\TextInput::make('pso_amount')->numeric()->default(0)->prefix('RM'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('jobSheet.number')->label('Job Sheet'),
                Infolists\Components\TextEntry::make('deliveryOrder.number')->label('DO'),
                Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                Infolists\Components\TextEntry::make('transfer_code'),
                Infolists\Components\TextEntry::make('task_type')->badge(),
                Infolists\Components\TextEntry::make('mainDriver.name')->label('Main driver'),
                Infolists\Components\TextEntry::make('subDriver.name')->label('Sub driver'),
                Infolists\Components\TextEntry::make('mainLorry.registration_no')->label('Main lorry'),
                Infolists\Components\TextEntry::make('subLorry.registration_no')->label('Sub lorry'),
                Infolists\Components\TextEntry::make('segment_route'),
                Infolists\Components\TextEntry::make('handover_status')->badge(),
                Infolists\Components\TextEntry::make('psi_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('pso_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('notes')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO'),
                Tables\Columns\TextColumn::make('jobSheet.number')->label('Job Sheet'),
                Tables\Columns\TextColumn::make('transfer_code'),
                Tables\Columns\TextColumn::make('task_type')->badge(),
                Tables\Columns\TextColumn::make('subDriver.name')->label('Sub driver'),
                Tables\Columns\TextColumn::make('psi_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('pso_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('handover_status')->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_handover')
                    ->label('Update handover')
                    ->icon('heroicon-o-arrows-right-left')
                    ->form([
                        Forms\Components\Select::make('handover_status')
                            ->options([
                                'pending' => 'Pending',
                                'released' => 'Released',
                                'collected' => 'Collected',
                                'completed' => 'Completed',
                            ])
                            ->required(),
                    ])
                    ->action(function (Subsheet $record, array $data) {
                        $record->update(['handover_status' => $data['handover_status']]);
                        Notification::make()->title('Handover updated')->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = \App\Support\CurrentCompany::id();
        if ($companyId) {
            $query->whereHas('deliveryOrder', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubsheets::route('/'),
            'create' => Pages\CreateSubsheet::route('/create'),
            'view' => Pages\ViewSubsheet::route('/{record}'),
        ];
    }
}
