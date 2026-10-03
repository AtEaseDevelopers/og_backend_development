<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Enums\CsnStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListConsignmentNotes extends ListRecords
{
    protected static string $resource = ConsignmentNoteResource::class;

    public function getHeading(): string
    {
        return 'CSN management';
    }

    public function getSubheading(): ?string
    {
        return 'Consignment notes created from billed orders: Pending lorry assignment → Assigned → In transit → Delivered. Claim, assign a lorry, transfer, and track each delivery.';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->withCount('deliveryOrders')
            ->with(['deliveryOrder.lorry', 'quotation']);
    }

    /** Stage tabs (section H): All / Unassigned / Claimed / Assigned / In transit / Delivered / Cancelled. */
    public function getTabs(): array
    {
        $awaiting = [CsnStatus::PendingAssignment->value, CsnStatus::Confirmed->value, CsnStatus::Draft->value];
        $base = fn (): Builder => ConsignmentNoteResource::getEloquentQuery();

        return [
            'all' => Tab::make('All')
                ->badge($base()->count()),
            'unassigned' => Tab::make('Unassigned')
                ->badge($base()->whereIn('status', $awaiting)->whereNull('claimed_by')->whereDoesntHave('deliveryOrder')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', $awaiting)->whereNull('claimed_by')->whereDoesntHave('deliveryOrder')),
            'claimed' => Tab::make('Claimed')
                ->badge($base()->whereIn('status', $awaiting)->whereNotNull('claimed_by')->whereDoesntHave('deliveryOrder')->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', $awaiting)->whereNotNull('claimed_by')->whereDoesntHave('deliveryOrder')),
            'assigned' => Tab::make('Assigned')
                ->badge($base()->where('status', CsnStatus::Assigned->value)->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', CsnStatus::Assigned->value)),
            'in_transit' => Tab::make('In transit')
                ->badge($base()->where('status', CsnStatus::InTransit->value)->count())
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', CsnStatus::InTransit->value)),
            'delivered' => Tab::make('Delivered')
                ->badge($base()->where('status', CsnStatus::Delivered->value)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', CsnStatus::Delivered->value)),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', CsnStatus::Cancelled->value)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New consignment note')];
    }
}
