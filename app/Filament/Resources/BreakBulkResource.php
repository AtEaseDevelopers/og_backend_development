<?php

namespace App\Filament\Resources;

use App\Domains\Delivery\Actions\AssignBreakBulkContinuation;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\BreakBulkResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class BreakBulkResource extends Resource
{
    protected static ?string $model = BreakBulk::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Break-Bulk';

    protected static ?int $navigationSort = 53;

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
                ->required(),
            Forms\Components\TextInput::make('location')->maxLength(255),
            Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
        ]);
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
                Infolists\Components\TextEntry::make('revoke_reason')->columnSpanFull(),
                Infolists\Components\TextEntry::make('released_at')->dateTime(),
                Infolists\Components\TextEntry::make('collected_at')->dateTime(),
                Infolists\Components\TextEntry::make('completed_at')->dateTime(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO'),
                Tables\Columns\TextColumn::make('location')->limit(30),
                Tables\Columns\TextColumn::make('originalDriver.name')->label('Original'),
                Tables\Columns\TextColumn::make('replacementDriver.name')->label('Replacement'),
                Tables\Columns\TextColumn::make('handover_status')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'warning',
                        'completed' => 'success',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'revoked' => 'Revoked',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('assign')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (BreakBulk $record) => $record->status === 'active')
                    ->form([
                        Forms\Components\Select::make('replacement_lorry_id')
                            ->label('Replacement lorry')
                            ->options(fn () => Lorry::query()->where('is_active', true)->pluck('registration_no', 'id'))
                            ->searchable(),
                        Forms\Components\Select::make('replacement_driver_id')
                            ->label('Replacement driver')
                            ->options(fn () => Driver::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\DatePicker::make('operating_date')->default(now()),
                    ])
                    ->action(function (BreakBulk $record, array $data) {
                        try {
                            app(AssignBreakBulkContinuation::class)->execute($record, $data, auth()->user());
                            Notification::make()->title('Break-Bulk continuation assigned')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('handover')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (BreakBulk $record) => $record->status === 'active')
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
                    ->action(function (BreakBulk $record, array $data) {
                        app(AssignBreakBulkContinuation::class)->updateHandover($record, $data['handover_status']);
                        Notification::make()->title('Handover updated')->success()->send();
                    }),
                Tables\Actions\Action::make('revoke')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (BreakBulk $record) => $record->status === 'active')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(function (BreakBulk $record, array $data) {
                        app(AssignBreakBulkContinuation::class)->revoke($record, $data['reason']);
                        Notification::make()->title('Break-Bulk revoked')->warning()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
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
            'index' => Pages\ListBreakBulks::route('/'),
            'create' => Pages\CreateBreakBulk::route('/create'),
            'view' => Pages\ViewBreakBulk::route('/{record}'),
        ];
    }
}
