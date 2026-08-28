<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Filament\Resources\BreakBulkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListBreakBulks extends ListRecords
{
    protected static string $resource = BreakBulkResource::class;

    protected static string $view = 'filament.resources.break-bulk-resource.pages.list-break-bulks';

    public ?string $filterSearch = null;

    public ?string $filterStatus = null;

    public ?string $filterSource = null;

    public function getHeading(): string
    {
        return 'Break-Bulk Record';
    }

    public function getSubheading(): ?string
    {
        return 'Review and manage driver-requested and manually created Break-Bulk records';
    }

    public function applyFilters(): void
    {
        $this->resetTable();
    }

    public function resetFilters(): void
    {
        $this->filterSearch = null;
        $this->filterStatus = null;
        $this->filterSource = null;
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Break-Bulk'),
        ];
    }

    public function getFilteredBreakBulkCount(): int
    {
        return (int) $this->getBreakBulkListingQuery()->count();
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'revoked' => 'Revoked',
        ];
    }

    /** @return array<string, string> */
    public function sourceFilterOptions(): array
    {
        return [
            'driver_request' => 'Driver Request',
            'manual_admin' => 'Manual Admin',
        ];
    }

    protected function getBreakBulkListingQuery(): Builder
    {
        return $this->applyBreakBulkFilters(
            BreakBulkResource::getEloquentQuery()
                ->with([
                    'deliveryOrder',
                    'consignmentNote',
                    'originalDriver',
                    'requestedByDriver',
                    'creator',
                ])
        );
    }

    protected function applyBreakBulkFilters(Builder $query): Builder
    {
        return $query
            ->when(filled($this->filterSearch), function (Builder $builder): void {
                $needle = trim((string) $this->filterSearch);

                $builder->where(function (Builder $q) use ($needle): void {
                    $q->where('number', 'like', '%'.$needle.'%')
                        ->orWhere('location', 'like', '%'.$needle.'%')
                        ->orWhere('reason', 'like', '%'.$needle.'%')
                        ->orWhereHas('deliveryOrder', fn (Builder $do) => $do->where('number', 'like', '%'.$needle.'%'))
                        ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'))
                        ->orWhereHas('originalDriver', fn (Builder $driver) => $driver->where('name', 'like', '%'.$needle.'%'))
                        ->orWhereHas('requestedByDriver', fn (Builder $driver) => $driver->where('name', 'like', '%'.$needle.'%'));
                });
            })
            ->when($this->filterStatus === 'pending', fn (Builder $builder) => $builder->where('status', 'active'))
            ->when($this->filterStatus === 'completed', fn (Builder $builder) => $builder->where('status', 'completed'))
            ->when($this->filterStatus === 'revoked', fn (Builder $builder) => $builder->where('status', 'revoked'))
            ->when($this->filterSource === 'driver_request', fn (Builder $builder) => $builder->whereNotNull('requested_by_driver_id'))
            ->when($this->filterSource === 'manual_admin', fn (Builder $builder) => $builder->whereNull('requested_by_driver_id'));
    }

    protected function getTableQuery(): Builder
    {
        return $this->getBreakBulkListingQuery();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->searchable(false)
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
