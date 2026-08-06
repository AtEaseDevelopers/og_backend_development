<?php

namespace App\Filament\Resources;

use App\Domains\Delivery\Models\MissingCsnLog;
use App\Filament\Resources\MissingCsnLogResource\Pages;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MissingCsnLogResource extends Resource
{
    protected static ?string $model = MissingCsnLog::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationGroup = 'Delivery';

    protected static ?string $navigationLabel = 'Missing CSNs';

    protected static ?int $navigationSort = 42;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('consignmentNote.number')->label('CSN'),
                Infolists\Components\TextEntry::make('sourceBranch.code')->label('Branch'),
                Infolists\Components\TextEntry::make('deliveryOrder.number')->label('DO'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('investigation_status'),
                Infolists\Components\TextEntry::make('marked_missing_at')->dateTime(),
                Infolists\Components\TextEntry::make('resolved_at')->dateTime(),
                Infolists\Components\TextEntry::make('follow_up_remarks')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('consignmentNote.number')->label('CSN')->searchable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending_return' => 'warning',
                        'missing' => 'danger',
                        'resolved' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('investigation_status'),
                Tables\Columns\TextColumn::make('marked_missing_at')->dateTime(),
                Tables\Columns\TextColumn::make('resolved_at')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending_return' => 'Pending return',
                    'missing' => 'Missing',
                    'resolved' => 'Resolved',
                ]),
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
            'index' => Pages\ListMissingCsnLogs::route('/'),
            'view' => Pages\ViewMissingCsnLog::route('/{record}'),
        ];
    }
}
