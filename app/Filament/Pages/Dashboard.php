<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BillingPerformanceStats;
use App\Filament\Widgets\OpsPerformanceStats;
use App\Support\CurrentCompany;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        $company = CurrentCompany::get();

        return $company ? 'Dashboard — '.$company->code : 'Dashboard';
    }

    public function getHeading(): string
    {
        $company = CurrentCompany::get();

        return $company
            ? 'Performance overview'
            : 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        $company = CurrentCompany::get();

        return $company
            ? $company->name.' · '.($company->branch?->name ?? $company->code)
            : null;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OpsPerformanceStats::class,
            BillingPerformanceStats::class,
        ];
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 1;
    }
}
