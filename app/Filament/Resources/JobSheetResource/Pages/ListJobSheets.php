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
use Illuminate\Support\Carbon;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListJobSheets extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = JobSheetResource::class;

    protected static string $view = 'filament.resources.job-sheet-resource.pages.list-job-sheets';

    public ?string $filterNumber = null;

    public ?string $filterTripNo = null;

    /** Operating date range ends (Y-m-d, empty = open); both start on today, so the list opens on today's sheets. */
    public ?string $filterOperatingFrom = null;

    public ?string $filterOperatingTo = null;

    public ?string $filterBranchId = null;

    public ?string $filterLorryId = null;

    public ?string $filterDriverId = null;

    /** '' any, 'none' = no delivery orders, 'some' = at least one */
    public ?string $filterTaskCount = null;

    public ?string $filterStatus = null;

    public ?int $selectedJobSheetId = null;

    public function mount(): void
    {
        parent::mount();

        $this->filterOperatingFrom = $this->filterOperatingTo = now()->format('Y-m-d');
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
        $this->filterTripNo = null;
        $this->filterOperatingFrom = $this->filterOperatingTo = now()->format('Y-m-d');
        $this->filterBranchId = null;
        $this->filterLorryId = null;
        $this->filterDriverId = null;
        $this->filterTaskCount = null;
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
        [$operatingFrom, $operatingTo] = $this->operatingDateRange();
        $tripNo = $this->tripNoSearch();

        // The Task Count column adds the delivery order count to the table query itself, so no withCount here
        return $query
            ->with(['operatingBranch', 'lorry', 'driver'])
            ->when(filled($this->filterNumber), fn (Builder $builder) => $builder->where(
                'number',
                'like',
                '%'.trim((string) $this->filterNumber).'%',
            ))
            ->when($tripNo !== '', fn (Builder $builder) => $builder->where(
                'trip_no',
                'like',
                '%'.$tripNo.'%',
            ))
            ->when($operatingFrom, fn (Builder $builder) => $builder->whereDate('operating_date', '>=', $operatingFrom))
            ->when($operatingTo, fn (Builder $builder) => $builder->whereDate('operating_date', '<=', $operatingTo))
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
            ->when($this->filterTaskCount === 'none', fn (Builder $builder) => $builder->doesntHave('deliveryOrders'))
            ->when($this->filterTaskCount === 'some', fn (Builder $builder) => $builder->has('deliveryOrders'))
            ->when(filled($this->filterStatus), fn (Builder $builder) => $builder->where(
                'status',
                $this->filterStatus,
            ));
    }

    /**
     * Valid Y-m-d ends of the operating date filter (null = open end), swapped when typed the wrong way round.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function operatingDateRange(): array
    {
        $valid = function (?string $value): ?string {
            $value = trim((string) $value);
            $date = $value !== '' ? rescue(fn () => Carbon::createFromFormat('!Y-m-d', $value), null, false) : null;

            return $date && $date->format('Y-m-d') === $value ? $value : null;
        };

        $from = $valid($this->filterOperatingFrom);
        $to = $valid($this->filterOperatingTo);

        return $from && $to && $from > $to ? [$to, $from] : [$from, $to];
    }

    /** The trip filter text without a typed "Trip" prefix, so "Trip 2" and "2" both match trip 2. */
    protected function tripNoSearch(): string
    {
        return trim((string) preg_replace('/^\s*trip\s*/i', '', (string) $this->filterTripNo));
    }

    /** Header badge text for the operating date filter, e.g. "07/10/2026" or "01/10/2026 – 07/10/2026". */
    public function getOperatingDateLabel(): string
    {
        [$from, $to] = $this->operatingDateRange();
        $format = fn (string $date): string => Carbon::createFromFormat('!Y-m-d', $date)->format('d/m/Y');

        return match (true) {
            $from && $to && $from === $to => $format($from),
            $from && $to => $format($from).' – '.$format($to),
            (bool) $from => 'From '.$format($from),
            (bool) $to => 'Until '.$format($to),
            default => 'All dates',
        };
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
    public function taskCountFilterOptions(): array
    {
        return [
            'none' => 'None',
            'some' => '1 or more',
        ];
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

    /** The id tie-breaker keeps column sorts with equal values (status, driver...) stable from page to page. */
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->searchable(false)
            ->filters([])
            ->defaultSort('id', 'desc')
            ->defaultKeySort()
            ->recordUrl(null)
            ->recordAction('selectJobSheetFromTable');
    }
}
