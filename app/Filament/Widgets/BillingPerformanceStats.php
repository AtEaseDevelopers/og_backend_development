<?php

namespace App\Filament\Widgets;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\InvoiceStatus;
use App\Support\CurrentCompany;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class BillingPerformanceStats extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Billing & fleet';
    }

    protected function getStats(): array
    {
        $companyId = CurrentCompany::id();
        $monthStart = now()->startOfMonth();

        $invoices = Invoice::query()->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
        $payments = Payment::query()->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));

        $outstanding = (clone $invoices)
            ->whereIn('status', [
                InvoiceStatus::Confirmed->value,
                InvoiceStatus::Outstanding->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->sum('total_amount');

        $invoicedMtd = (clone $invoices)
            ->whereDate('invoice_date', '>=', $monthStart)
            ->sum('total_amount');

        $collectedMtd = (clone $payments)
            ->whereDate('created_at', '>=', $monthStart)
            ->sum('amount');

        $activeLorries = Lorry::query()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->count();

        $availableLorries = Lorry::query()
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();

        return [
            Stat::make('Outstanding invoices', 'RM '.number_format((float) $outstanding, 2))
                ->description('Confirmed / outstanding / partially paid')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color(((float) $outstanding) > 0 ? 'warning' : 'success'),

            Stat::make('Invoiced (MTD)', 'RM '.number_format((float) $invoicedMtd, 2))
                ->description('Invoice date this month')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('primary'),

            Stat::make('Collected (MTD)', 'RM '.number_format((float) $collectedMtd, 2))
                ->description('Payments recorded this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Fleet available', "{$availableLorries} / {$activeLorries}")
                ->description('Active lorries marked available')
                ->descriptionIcon('heroicon-m-truck')
                ->color($availableLorries > 0 ? 'success' : 'gray'),
        ];
    }
}
