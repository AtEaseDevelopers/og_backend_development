<?php

namespace App\Filament\Resources\FailedDeliveryResource\Pages;

use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Pages\DeliveryMonitoring;
use App\Filament\Resources\FailedDeliveryResource;
use App\Support\CurrentCompany;
use App\Support\FailedDeliveryReviewData;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFailedDeliveries extends ListRecords
{
    protected static string $resource = FailedDeliveryResource::class;

    protected static string $view = 'filament.resources.failed-delivery-resource.pages.list-failed-deliveries';

    public ?string $filterDeliveryDate = null;

    public ?string $filterBranchId = null;

    public ?string $filterDriverId = null;

    public ?string $filterLorryId = null;

    public ?string $filterJobSheetId = null;

    public ?string $filterSearch = null;

    public function mount(): void
    {
        parent::mount();

        $this->filterDeliveryDate = now()->format('Y-m-d');
    }

    public function getHeading(): string
    {
        return 'Failed Delivery Review';
    }

    public function getSubheading(): ?string
    {
        return 'Review failed delivery tasks, failure information and supporting evidence.';
    }

    public function applyFilters(): void
    {
        // Re-render with current filter state.
    }

    public function resetFilters(): void
    {
        $this->filterDeliveryDate = now()->format('Y-m-d');
        $this->filterBranchId = null;
        $this->filterDriverId = null;
        $this->filterLorryId = null;
        $this->filterJobSheetId = null;
        $this->filterSearch = null;
    }

    /** @return array<string, mixed> */
    public function getReviewData(): array
    {
        return app(FailedDeliveryReviewData::class)->for($this->currentFilters());
    }

    /** @return array<string, mixed> */
    protected function currentFilters(): array
    {
        return [
            'delivery_date' => $this->filterDeliveryDate,
            'branch_id' => $this->filterBranchId,
            'driver_id' => $this->filterDriverId,
            'lorry_id' => $this->filterLorryId,
            'job_sheet_id' => $this->filterJobSheetId,
            'search' => $this->filterSearch,
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

        if (filled($this->filterDeliveryDate)) {
            $query->whereDate('operating_date', $this->filterDeliveryDate);
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToMonitoring')
                ->label('Back to Delivery Monitoring')
                ->color('gray')
                ->url(fn (): string => Filament::getTenant()
                    ? DeliveryMonitoring::getUrl([], true, null, Filament::getTenant())
                    : DeliveryMonitoring::getUrl()),
        ];
    }
}
