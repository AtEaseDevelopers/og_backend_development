<?php

namespace App\Filament\Resources;

use App\Domains\Delivery\Actions\ReassignFailedDelivery;
use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\FailedDeliveryResource\Pages;
use App\Support\CurrentCompany;
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

class FailedDeliveryResource extends Resource
{
    protected static ?string $model = FailedDelivery::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Failed Delivery Review';

    protected static ?int $navigationSort = 55;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('deliveryOrder.number')->label('Failed DO'),
                Infolists\Components\TextEntry::make('driver.name')->label('Driver'),
                Infolists\Components\TextEntry::make('reason')->columnSpanFull(),
                Infolists\Components\TextEntry::make('remarks')->columnSpanFull(),
                Infolists\Components\TextEntry::make('reassignment_option')->badge(),
                Infolists\Components\TextEntry::make('replacementDeliveryOrder.number')->label('Replacement DO'),
                Infolists\Components\TextEntry::make('failed_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO')->searchable(),
                Tables\Columns\TextColumn::make('driver.name'),
                Tables\Columns\TextColumn::make('reason')->limit(40),
                Tables\Columns\TextColumn::make('reassignment_option')->badge()
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('replacementDeliveryOrder.number')->label('Replacement DO'),
                Tables\Columns\TextColumn::make('failed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('failed_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('reassigned')
                    ->label('Reassigned')
                    ->nullable()
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('replacement_do_id'),
                        false: fn ($q) => $q->whereNull('replacement_do_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reassign')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (FailedDelivery $record) => blank($record->replacement_do_id))
                    ->form([
                        Forms\Components\Select::make('option')
                            ->label('Reassignment option')
                            ->options([
                                'standard' => 'Standard — same DO, original driver no commission',
                                'duplicate' => 'Duplicate — new DO, dual commission eligible',
                            ])
                            ->required()
                            ->helperText('Standard moves the failed DO. Duplicate keeps the failed DO and creates a linked replacement.'),
                        Forms\Components\Select::make('lorry_id')
                            ->label('Replacement lorry')
                            ->options(fn () => Lorry::query()
                                ->with('branch')
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Lorry $lorry) => [
                                    $lorry->id => $lorry->registration_no.' ['.$lorry->branch?->code.']',
                                ]))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('driver_id')
                            ->label('Replacement driver (optional)')
                            ->options(fn () => Driver::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\DatePicker::make('operating_date')->default(now()),
                    ])
                    ->action(function (FailedDelivery $record, array $data) {
                        try {
                            $do = app(ReassignFailedDelivery::class)->execute(
                                $record,
                                $data['option'],
                                Lorry::findOrFail($data['lorry_id']),
                                auth()->user(),
                                $data['operating_date'] ?? null,
                                $data['driver_id'] ?? null,
                            );
                            Notification::make()
                                ->title('Reassigned ('.$data['option'].')')
                                ->body('Active DO: '.$do->number)
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
            'index' => Pages\ListFailedDeliveries::route('/'),
            'view' => Pages\ViewFailedDelivery::route('/{record}'),
        ];
    }
}
