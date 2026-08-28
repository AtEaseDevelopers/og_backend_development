<?php

namespace App\Filament\Resources;

use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Filament\Resources\BreakBulkResource\Pages;
use App\Support\BreakBulkViewData;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BreakBulkResource extends Resource
{
    protected static ?string $model = BreakBulk::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Break-Bulk Record';

    protected static ?int $navigationSort = 53;

    protected static ?string $modelLabel = 'Break-Bulk';

    protected static ?string $pluralModelLabel = 'Break-Bulk Records';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Break-Bulk Details')
                ->description('Create a manual admin Break-Bulk record for a delivery order that needs reassignment at an intermediate location.')
                ->schema([
                    Forms\Components\Select::make('delivery_order_id')
                        ->label('Delivery Order')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(fn (): array => static::eligibleDeliveryOrderOptions())
                        ->getSearchResultsUsing(fn (string $search): array => static::eligibleDeliveryOrderOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::eligibleDeliveryOrderLabel((int) $value))
                        ->helperText('Only delivery orders on a job sheet without an active Break-Bulk are listed.'),
                    Forms\Components\TextInput::make('location')
                        ->label('Intermediate Location')
                        ->placeholder('e.g. Ipoh Transit Hub')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->placeholder('e.g. Unexpected Delivery Change')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    /** @return array<int, string> */
    public static function eligibleDeliveryOrderOptions(?string $search = null): array
    {
        return static::eligibleDeliveryOrdersQuery($search)
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (DeliveryOrder $do): array => [
                $do->id => static::formatDeliveryOrderOption($do),
            ])
            ->all();
    }

    public static function eligibleDeliveryOrderLabel(int $deliveryOrderId): ?string
    {
        $do = static::eligibleDeliveryOrdersQuery()
            ->whereKey($deliveryOrderId)
            ->first();

        return $do ? static::formatDeliveryOrderOption($do) : null;
    }

    public static function formatDeliveryOrderOption(DeliveryOrder $do): string
    {
        $do->loadMissing(['consignmentNote', 'driver', 'jobSheet']);

        $parts = array_filter([
            $do->number,
            $do->consignmentNote?->number ? 'CSN '.$do->consignmentNote->number : null,
            $do->driver?->name ? 'Driver '.$do->driver->name : null,
            $do->jobSheet?->number ? 'JS '.$do->jobSheet->number : null,
        ]);

        return implode(' · ', $parts);
    }

    public static function eligibleDeliveryOrdersQuery(?string $search = null): Builder
    {
        $query = DeliveryOrder::query()
            ->whereNotNull('job_sheet_id')
            ->whereDoesntHave('breakBulks', fn (Builder $q) => $q->where('status', 'active'))
            ->with(['consignmentNote', 'driver', 'jobSheet'])
            ->orderByDesc('id');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        if (filled($search)) {
            $needle = trim($search);

            $query->where(function (Builder $q) use ($needle): void {
                $q->where('number', 'like', '%'.$needle.'%')
                    ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'))
                    ->orWhereHas('driver', fn (Builder $driver) => $driver->where('name', 'like', '%'.$needle.'%'))
                    ->orWhereHas('jobSheet', fn (Builder $js) => $js->where('number', 'like', '%'.$needle.'%'));
            });
        }

        return $query;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('deliveryOrder.number')->label('DO'),
                Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                Infolists\Components\TextEntry::make('jobSheet.number')->label('Job Sheet'),
                Infolists\Components\TextEntry::make('originalDriver.name')->label('Original driver'),
                Infolists\Components\TextEntry::make('originalLorry.registration_no')->label('Original lorry'),
                Infolists\Components\TextEntry::make('replacementDriver.name')->label('Replacement driver'),
                Infolists\Components\TextEntry::make('replacementLorry.registration_no')->label('Replacement lorry'),
                Infolists\Components\TextEntry::make('location'),
                Infolists\Components\TextEntry::make('reason')->columnSpanFull(),
                Infolists\Components\TextEntry::make('handover_status')->badge(),
                Infolists\Components\TextEntry::make('status')->badge(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Break-Bulk No.')
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('deliveryOrder.number')
                    ->label('Related DO / CSN')
                    ->html()
                    ->formatStateUsing(fn ($state, BreakBulk $record): string => '<div class="bb-cell-primary">'.e($record->deliveryOrder?->number ?? '—').'</div>'
                        .'<div class="bb-cell-secondary">'.e($record->consignmentNote?->number ?? '—').'</div>'),
                Tables\Columns\TextColumn::make('originalDriver.name')
                    ->label('Driver')
                    ->default('—'),
                Tables\Columns\TextColumn::make('location')
                    ->label('Intermediate Location')
                    ->limit(40)
                    ->default('—'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->default('—'),
                Tables\Columns\TextColumn::make('requested_by_driver_id')
                    ->label('Source')
                    ->formatStateUsing(fn ($state, BreakBulk $record): string => BreakBulkViewData::sourceLabel($record)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date / Time')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, BreakBulk $record): string => BreakBulkViewData::displayStatus($record))
                    ->color(fn ($state, BreakBulk $record): string => BreakBulkViewData::displayStatusColor($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->label('')
                    ->tooltip('View details'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = CurrentCompany::id();
        if ($companyId) {
            $query->whereHas('deliveryOrder', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBreakBulks::route('/'),
            'create' => Pages\CreateBreakBulk::route('/create'),
            'view' => Pages\ViewBreakBulk::route('/{record}'),
        ];
    }
}
