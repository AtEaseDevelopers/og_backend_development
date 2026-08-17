<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsignmentNotes extends ListRecords
{
    protected static string $resource = ConsignmentNoteResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()
            ->withCount('deliveryOrders')
            ->with(['deliveryOrder.lorry']);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
