<?php

namespace App\Filament\Resources\MissingCsnLogResource\Pages;

use App\Domains\Delivery\Actions\FlagMissingCsns;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\MissingCsnLogResource;
use App\Support\CurrentCompany;
use App\Support\MissingCsnMonitoringData;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMissingCsnLogs extends ListRecords
{
    protected static string $resource = MissingCsnLogResource::class;

    protected static string $view = 'filament.resources.missing-csn-log-resource.pages.list-missing-csn-logs';

    public ?string $filterMonitoringDate = null;

    public ?string $filterBranchId = null;

    public ?string $filterDriverId = null;

    public ?string $filterLorryId = null;

    public ?string $filterCustomerId = null;

    public ?string $filterCsnSearch = null;

    public ?string $filterJobSheetId = null;

    public ?string $filterStatus = null;

    public int $monitoringPage = 1;

    public function mount(): void
    {
        parent::mount();

        $this->filterMonitoringDate = now()->format('Y-m-d');
    }

    public function getHeading(): string
    {
        return 'Missing CSN Monitoring';
    }

    public function getSubheading(): ?string
    {
        return 'Monitor unreturned CSNs and identify records that exceed the configured return period.';
    }

    public function applyFilters(): void
    {
        $this->monitoringPage = 1;
    }

    public function resetFilters(): void
    {
        $this->filterMonitoringDate = now()->format('Y-m-d');
        $this->filterBranchId = null;
        $this->filterDriverId = null;
        $this->filterLorryId = null;
        $this->filterCustomerId = null;
        $this->filterCsnSearch = null;
        $this->filterJobSheetId = null;
        $this->filterStatus = null;
        $this->monitoringPage = 1;
    }

    public function refreshMonitoring(): void
    {
        $logs = app(FlagMissingCsns::class)->execute();

        Notification::make()
            ->title('Monitoring refreshed')
            ->body($logs->count().' CSN(s) marked missing')
            ->success()
            ->send();
    }

    public function viewMissingOnly(): void
    {
        $this->filterStatus = 'missing';
        $this->monitoringPage = 1;
    }

    public function setMonitoringPage(int $page): void
    {
        $this->monitoringPage = max(1, $page);
    }

    /** @return array<string, mixed> */
    public function getMonitoringData(): array
    {
        return app(MissingCsnMonitoringData::class)->for($this->currentFilters());
    }

    /** @return array<string, mixed> */
    protected function currentFilters(): array
    {
        return [
            'monitoring_date' => $this->filterMonitoringDate,
            'branch_id' => $this->filterBranchId,
            'driver_id' => $this->filterDriverId,
            'lorry_id' => $this->filterLorryId,
            'customer_id' => $this->filterCustomerId,
            'csn_search' => $this->filterCsnSearch,
            'job_sheet_id' => $this->filterJobSheetId,
            'status' => $this->filterStatus,
            'page' => $this->monitoringPage,
        ];
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
    public function jobSheetFilterOptions(): array
    {
        $query = JobSheet::query()->orderByDesc('id');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->limit(100)->pluck('number', 'id')->all();
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

    /** @return array<int|string, string> */
    public function lorryFilterOptions(): array
    {
        $query = Lorry::query()->where('is_active', true)->orderBy('registration_no');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('registration_no', 'id')->all();
    }

    /** @return array<int|string, string> */
    public function customerFilterOptions(): array
    {
        $query = Customer::query()->where('status', 'active')->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('company_name', 'id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshMonitoring')
                ->label('Refresh Monitoring')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshMonitoring()),
            Actions\Action::make('viewMissingCsns')
                ->label('View Missing CSNs')
                ->icon('heroicon-o-eye')
                ->action(fn () => $this->viewMissingOnly()),
        ];
    }
}
