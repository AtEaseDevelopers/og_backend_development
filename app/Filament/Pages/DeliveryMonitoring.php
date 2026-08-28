<?php

namespace App\Filament\Pages;

use App\Domains\Delivery\Actions\FlagIncompleteDeliveries;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\DeliveryOrderStatus;
use App\Support\CurrentCompany;
use App\Support\DeliveryMonitoringData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DeliveryMonitoring extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Delivery Monitoring';

    protected static ?int $navigationSort = 56;

    protected static string $view = 'filament.pages.delivery-monitoring';

    public ?string $filterDeliveryDate = null;

    public ?string $filterBranchId = null;

    public ?string $filterStatus = null;

    public ?string $filterSearch = null;

    public ?string $filterJobSheetId = null;

    public ?string $filterDriverId = null;

    public ?string $filterLorryId = null;

    public function mount(): void
    {
        $this->filterDeliveryDate = now()->format('Y-m-d');
    }

    public function getHeading(): string
    {
        return 'Delivery Monitoring';
    }

    public function getSubheading(): ?string
    {
        return 'Monitor delivery tasks and identify scheduled tasks requiring further action.';
    }

    public function applyFilters(): void
    {
        // Re-render with current filter state.
    }

    public function resetFilters(): void
    {
        $this->filterDeliveryDate = now()->format('Y-m-d');
        $this->filterBranchId = null;
        $this->filterStatus = null;
        $this->filterSearch = null;
        $this->filterJobSheetId = null;
        $this->filterDriverId = null;
        $this->filterLorryId = null;
    }

    public function runFlag(): void
    {
        $date = Carbon::parse($this->filterDeliveryDate ?? now());
        $alerts = app(FlagIncompleteDeliveries::class)->execute($date, notify: true);

        Notification::make()
            ->title('Incomplete deliveries flagged')
            ->body($alerts->count().' task(s) for '.$date->format('d/m/Y'))
            ->success()
            ->send();
    }

    /** @return array<string, mixed> */
    public function getMonitoringData(): array
    {
        return app(DeliveryMonitoringData::class)->for($this->currentFilters());
    }

    /** @return array<string, mixed> */
    protected function currentFilters(): array
    {
        return [
            'delivery_date' => $this->filterDeliveryDate,
            'branch_id' => $this->filterBranchId,
            'status' => $this->filterStatus,
            'search' => $this->filterSearch,
            'job_sheet_id' => $this->filterJobSheetId,
            'driver_id' => $this->filterDriverId,
            'lorry_id' => $this->filterLorryId,
        ];
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return collect(DeliveryOrderStatus::cases())
            ->mapWithKeys(fn (DeliveryOrderStatus $status) => [$status->value => $status->getLabel()])
            ->all();
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'dispatcher']);
    }
}
