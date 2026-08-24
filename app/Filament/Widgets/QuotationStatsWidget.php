<?php

namespace App\Filament\Widgets;

use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Support\CurrentCompany;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class QuotationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $companyId = CurrentCompany::id();
        $monthStart = now()->startOfMonth();

        $quotes = Quotation::query()->when(
            $companyId,
            fn (Builder $query) => $query->where('company_id', $companyId),
        );

        $total = (clone $quotes)->count();

        $pendingApproval = (clone $quotes)
            ->where('status', QuotationStatus::PendingApproval)
            ->count();

        $approvedThisMonth = (clone $quotes)
            ->whereIn('status', [QuotationStatus::Confirmed, QuotationStatus::Accepted])
            ->where(function (Builder $query) use ($monthStart): void {
                $query
                    ->where('confirmed_at', '>=', $monthStart)
                    ->orWhere(function (Builder $query) use ($monthStart): void {
                        $query
                            ->whereNull('confirmed_at')
                            ->where('updated_at', '>=', $monthStart);
                    });
            })
            ->count();

        return [
            Stat::make('Total quotes', (string) number_format($total))
                ->description('All quotations in the system')
                ->descriptionIcon('heroicon-m-document-text', IconPosition::After)
                ->color('gray'),

            Stat::make('Pending approval', (string) number_format($pendingApproval))
                ->description('Awaiting branch manager review')
                ->descriptionIcon('heroicon-m-clock', IconPosition::After)
                ->color('warning'),

            Stat::make('Approved this month', (string) number_format($approvedThisMonth))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::After)
                ->color('success'),
        ];
    }
}
