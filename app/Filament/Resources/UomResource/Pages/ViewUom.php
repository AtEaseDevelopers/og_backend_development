<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Resources\UomResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUom extends ViewRecord
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('UOM details')->schema([
                Infolists\Components\TextEntry::make('code'),
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\IconEntry::make('is_active')->boolean(),
            ])->columns(3),
            Infolists\Components\Section::make('Location rate tiers')->schema([
                Infolists\Components\RepeatableEntry::make('rateTiers')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('location.name')->label('Location'),
                        Infolists\Components\TextEntry::make('min_qty')->label('Min qty'),
                        Infolists\Components\TextEntry::make('max_qty')
                            ->label('Max qty')
                            ->placeholder('And above'),
                        Infolists\Components\TextEntry::make('price')
                            ->label('Price')
                            ->money('MYR'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
