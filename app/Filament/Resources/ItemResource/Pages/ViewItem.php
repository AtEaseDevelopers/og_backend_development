<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Item details')->schema([
                Infolists\Components\TextEntry::make('code'),
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('category.name')->label('Category'),
                Infolists\Components\TextEntry::make('default_uom')->label('Default UOM'),
                Infolists\Components\IconEntry::make('is_active')->boolean(),
            ])->columns(3),
            Infolists\Components\Section::make('Location rates')->schema([
                Infolists\Components\RepeatableEntry::make('rates')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('location.name')->label('Location'),
                        Infolists\Components\TextEntry::make('price')
                            ->label('Price')
                            ->money('MYR'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
