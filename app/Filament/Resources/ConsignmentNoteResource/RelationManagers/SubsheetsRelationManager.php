<?php

namespace App\Filament\Resources\ConsignmentNoteResource\RelationManagers;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubsheetsRelationManager extends RelationManager
{
    protected static string $relationship = 'subsheets';

    protected static ?string $title = 'Subsheets';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable(),
                Tables\Columns\TextColumn::make('subLorry.registration_no')->label('Sub lorry'),
                Tables\Columns\TextColumn::make('subDriver.name')->label('Sub driver'),
                Tables\Columns\TextColumn::make('task_type')->badge(),
                Tables\Columns\TextColumn::make('transfer_code'),
                Tables\Columns\TextColumn::make('handover_status')->badge(),
                Tables\Columns\TextColumn::make('psi_amount')->money('MYR'),
            ])
            ->headerActions([])
            ->actions([
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
                    ->action(function ($record, array $data) {
                        $record->update(['handover_status' => $data['handover_status']]);
                        Notification::make()->title('Handover updated')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No subsheets yet')
            ->emptyStateDescription('Enable “Additional pickup task” on the CSN, assign a main lorry, then save to create subsheets for assisting lorries.');
    }
}
