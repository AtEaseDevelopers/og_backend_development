<?php

namespace App\Filament\Widgets;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\CsnStatus;
use App\Enums\JobSheetStatus;
use App\Enums\QuotationStatus;
use App\Support\CurrentCompany;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class OpsPerformanceStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        $company = CurrentCompany::get();

        return $company
            ? 'Overall performance — '.$company->name
            : 'Overall performance';
    }

    protected function getDescription(): ?string
    {
        return 'Live snapshot for this company (today + this month).';
    }

    protected function getStats(): array
    {
        $companyId = CurrentCompany::id();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $csns = ConsignmentNote::query()->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
        $quotes = Quotation::query()->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
        $jobs = JobSheet::query()->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));

        $deliveredMonth = (clone $csns)
            ->where('status', CsnStatus::Delivered)
            ->where('updated_at', '>=', $monthStart)
            ->count();

        $activeCsns = (clone $csns)->whereIn('status', [
            CsnStatus::Confirmed,
            CsnStatus::Assigned,
            CsnStatus::InTransit,
        ])->count();

        $inTransitCsns = (clone $csns)->where('status', CsnStatus::InTransit)->count();

        $deliveredToday = (clone $csns)
            ->where('status', CsnStatus::Delivered)
            ->whereDate('updated_at', $today)
            ->count();

        $openQuotes = (clone $quotes)->whereIn('status', [
            QuotationStatus::Draft,
            QuotationStatus::Sent,
            QuotationStatus::Accepted,
            QuotationStatus::Confirmed,
            QuotationStatus::PendingApproval,
        ])->count();

        $convertedMonth = (clone $quotes)
            ->where('status', QuotationStatus::Converted)
            ->where('updated_at', '>=', $monthStart)
            ->count();

        $activeJobsToday = (clone $jobs)
            ->whereDate('operating_date', $today)
            ->whereIn('status', [
                JobSheetStatus::Draft->value,
                JobSheetStatus::InTransit->value,
            ])
            ->count();

        $missingCsns = MissingCsnLog::query()
            ->whereNull('resolved_at')
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->count();

        return [
            Stat::make('Active CSNs', (string) $activeCsns)
                ->description('Confirmed / assigned / in transit')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('CSNs in transit', (string) $inTransitCsns)
                ->description('Currently moving')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('Delivered today', (string) $deliveredToday)
                ->description("{$deliveredMonth} delivered this month")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Open quotations', (string) $openQuotes)
                ->description("{$convertedMonth} converted this month")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Job sheets today', (string) $activeJobsToday)
                ->description('Draft or in transit for '.$today)
                ->descriptionIcon('heroicon-m-map')
                ->color('gray'),

            Stat::make('Missing CSNs', (string) $missingCsns)
                ->description('Unresolved return / missing logs')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($missingCsns > 0 ? 'danger' : 'success'),
        ];
    }
}
