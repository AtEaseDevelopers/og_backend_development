<?php

namespace App\Filament\Resources;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use App\Filament\Resources\DeliveryOrderResource\Pages;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Delivery Orders';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                Infolists\Components\TextEntry::make('sourceBranch.name')->label('Source Branch'),
                Infolists\Components\TextEntry::make('jobSheet.number')->label('Job Sheet'),
                Infolists\Components\TextEntry::make('lorry.registration_no')->label('Lorry'),
                Infolists\Components\TextEntry::make('driver.name')->label('Driver'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('tracking_token')->label('Tracking Token')->copyable(),
                Infolists\Components\TextEntry::make('delivered_at')->dateTime(),
                Infolists\Components\TextEntry::make('failed_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('consignmentNote.number')->label('CSN'),
                Tables\Columns\TextColumn::make('sourceBranch.name')->label('Branch'),
                Tables\Columns\TextColumn::make('lorry.registration_no')->label('Lorry'),
                Tables\Columns\TextColumn::make('driver.name'),
                Tables\Columns\TextColumn::make('jobSheet.number')->label('Job Sheet'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('delivered_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DeliveryOrderStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => ucfirst(str_replace('_', ' ', $c->value))]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrders::route('/'),
            'view' => Pages\ViewDeliveryOrder::route('/{record}'),
        ];
    }
}
