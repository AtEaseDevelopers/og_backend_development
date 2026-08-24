<?php

namespace App\Filament\Resources\JobSheetResource\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\JobSheetStatus;
use App\Filament\Resources\JobSheetResource;
use App\Support\CurrentCompany;
use App\Support\JobSheetListData;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListJobSheets extends ListRecords
{
    protected static string $resource = JobSheetResource::class;

    protected static string $view = 'filament.resources.job-sheet-resource.pages.list-job-sheets';

    public ?string $filterNumber = null;

    public ?string $filterOperatingDate = null;

    public ?string $filterBranchId = null;

    public ?string $filterLorryId = null;

    public ?string $filterDriverId = null;

    public ?string $filterStatus = null;

    public ?int $selectedJobSheetId = null;

    public function mount(): void
    {
        parent::mount();

        $this->filterOperatingDate = now()->format('Y-m-d');
        $this->selectFirstJobSheet();
    }

    public function selectJobSheetFromTable(string $recordKey): void
    {
        $this->selectJobSheet((int) $recordKey);
    }

    public function getHeading(): string
    {
        return 'Job Sheet Management';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function applyFilters(): void
    {
        $this->resetTable();
        $this->selectFirstJobSheet();
    }

    public function resetFilters(): void
    {
        $this->filterNumber = null;
        $this->filterOperatingDate = now()->format('Y-m-d');
        $this->filterBranchId = null;
        $this->filterLorryId = null;
        $this->filterDriverId = null;
        $this->filterStatus = null;
        $this->resetTable();
        $this->selectFirstJobSheet();
    }

    public function selectJobSheet(int $jobSheetId): void
    {
        $this->selectedJobSheetId = $jobSheetId;
    }

    protected function selectFirstJobSheet(): void
    {
        $firstId = $this->getJobSheetListingQuery()
            ->orderByDesc('id')
            ->value('id');

        $this->selectedJobSheetId = $firstId ? (int) $firstId : null;
    }

    protected function getJobSheetListingQuery(): Builder
    {
        return $this->applyJobSheetFilters(
            JobSheetResource::getEloquentQuery()
        );
    }

    protected function applyJobSheetFilters(Builder $query): Builder
    {
        return $query
            ->withCount('deliveryOrders')
            ->with(['operatingBranch', 'lorry', 'driver'])
            ->when(filled($this->filterNumber), fn (Builder $builder) => $builder->where(
                'number',
                'like',
                '%'.trim((string) $this->filterNumber).'%',
            ))
            ->when(filled($this->filterOperatingDate), fn (Builder $builder) => $builder->whereDate(
                'operating_date',
                $this->filterOperatingDate,
            ))
            ->when(filled($this->filterBranchId), fn (Builder $builder) => $builder->where(
                'operating_branch_id',
                $this->filterBranchId,
            ))
            ->when(filled($this->filterLorryId), fn (Builder $builder) => $builder->where(
                'lorry_id',
                $this->filterLorryId,
            ))
            ->when(filled($this->filterDriverId), fn (Builder $builder) => $builder->where(
                'driver_id',
                $this->filterDriverId,
            ))
            ->when(filled($this->filterStatus), fn (Builder $builder) => $builder->where(
                'status',
                $this->filterStatus,
            ));
    }

    /** @return array<string, mixed>|null */
    public function getSelectedJobSheetPanel(): ?array
    {
        return app(JobSheetListData::class)->selectedPanel($this->selectedJobSheetId);
    }

    public function getFilteredJobSheetCount(): int
    {
        return (int) $this->getJobSheetListingQuery()->count();
    }

    /** @return array<int|string, string> */
    public function branchFilterOptions(): array
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    public function lorryFilterOptions(): array
    {
        return Lorry::query()
            ->when($companyId = CurrentCompany::id(), fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('registration_no')
            ->pluck('registration_no', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    public function driverFilterOptions(): array
    {
        $query = Driver::query()->where('is_active', true)->orderBy('name');

        if ($companyId = CurrentCompany::id()) {
            $query->where(function (Builder $q) use ($companyId): void {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return collect(JobSheetStatus::cases())
            ->mapWithKeys(fn (JobSheetStatus $status) => [$status->value => $status->getLabel()])
            ->all();
    }

    protected function getTableQuery(): Builder
    {
        return $this->getJobSheetListingQuery();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->searchable(false)
            ->filters([])
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction('selectJobSheetFromTable');
    }
}
