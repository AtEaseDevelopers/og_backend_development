<?php

namespace App\Filament\Resources;

use App\Domains\Commission\Actions\AdjustCommissionSlip;
use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Commission\Models\CommissionSlip;
use App\Filament\Resources\CommissionSlipResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class CommissionSlipResource extends Resource
{
    protected static ?string $model = CommissionSlip::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Commission';

    protected static ?string $navigationLabel = 'Commission Slips';

    protected static ?int $navigationSort = 32;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('batch.month')->label('Batch month'),
                Infolists\Components\TextEntry::make('sourceBranch.code')->label('Branch'),
                Infolists\Components\TextEntry::make('driver.name')->label('Driver'),
                Infolists\Components\TextEntry::make('lorry.registration_no')->label('Lorry'),
                Infolists\Components\TextEntry::make('system_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('final_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('psi_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('pso_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('deductions')->money('MYR'),
                Infolists\Components\TextEntry::make('status')->badge(),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('lines')
                ->schema([
                    Infolists\Components\TextEntry::make('deliveryOrder.number')->label('DO'),
                    Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                    Infolists\Components\TextEntry::make('line_type')->badge(),
                    Infolists\Components\TextEntry::make('amount')->money('MYR'),
                    Infolists\Components\IconEntry::make('is_eligible')->boolean()->label('Eligible'),
                    Infolists\Components\IconEntry::make('is_hidden')->boolean()->label('Hidden'),
                    Infolists\Components\IconEntry::make('is_carry_forward')->boolean()->label('Carry fwd'),
                    Infolists\Components\TextEntry::make('notes')->columnSpanFull(),
                ])
                ->columns(4),
            Infolists\Components\RepeatableEntry::make('adjustments')
                ->schema([
                    Infolists\Components\TextEntry::make('amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('reason'),
                    Infolists\Components\TextEntry::make('adjustedBy.name')->label('By'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('PO / PI')->schema([
                Infolists\Components\TextEntry::make('purchaseOrder.po_number')->label('PO'),
                Infolists\Components\TextEntry::make('purchaseOrder.pi_number')->label('PI'),
                Infolists\Components\TextEntry::make('purchaseOrder.amount')->money('MYR'),
                Infolists\Components\TextEntry::make('purchaseOrder.autocount_sync_status')->label('AutoCount'),
            ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable(),
                Tables\Columns\TextColumn::make('batch.month')->label('Month'),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('driver.name')->searchable(),
                Tables\Columns\TextColumn::make('system_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('final_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('adjust')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (CommissionSlip $record) => $record->batch?->status === 'draft')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->helperText('Negative = deduction (e.g. COD shortage)'),
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (CommissionSlip $record, array $data) {
                        try {
                            app(AdjustCommissionSlip::class)->adjustAmount(
                                $record,
                                (float) $data['amount'],
                                $data['reason'],
                                auth()->user()
                            );
                            Notification::make()->title('Adjustment applied')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('hide_line')
                    ->label('Hide line')
                    ->icon('heroicon-o-eye-slash')
                    ->visible(fn (CommissionSlip $record) => $record->batch?->status === 'draft')
                    ->form([
                        Forms\Components\Select::make('line_id')
                            ->label('Line')
                            ->options(fn (CommissionSlip $record) => $record->lines()
                                ->where('is_hidden', false)
                                ->with('deliveryOrder')
                                ->get()
                                ->mapWithKeys(fn (CommissionLineItem $line) => [
                                    $line->id => ($line->deliveryOrder?->number ?? 'n/a').' — RM '.number_format((float) $line->amount, 2),
                                ]))
                            ->required(),
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (CommissionSlip $record, array $data) {
                        try {
                            $line = CommissionLineItem::query()->findOrFail($data['line_id']);
                            app(AdjustCommissionSlip::class)->hideLine($line, $data['reason']);
                            Notification::make()->title('Line hidden')->success()->send();
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
            'index' => Pages\ListCommissionSlips::route('/'),
            'view' => Pages\ViewCommissionSlip::route('/{record}'),
        ];
    }
}
