<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeliveryMonitoringData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $date = filled($filters['delivery_date'] ?? null)
            ? Carbon::parse((string) $filters['delivery_date'])->startOfDay()
            : now()->startOfDay();

        $query = $this->taskQuery($filters, $date);
        $tasks = (clone $query)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeliveryOrder $do): array => $this->formatTaskRow($do, $date));

        $stats = $this->stats(clone $query);
        $incomplete = $this->incompleteTasks($filters, $date);

        return [
            'selected_date' => $date->format('Y-m-d'),
            'selected_date_label' => $date->format('d/m/Y'),
            'stats' => $stats,
            'tasks' => $tasks->all(),
            'status_legend' => $this->statusLegend(),
            'incomplete' => $incomplete->all(),
            'incomplete_count' => $incomplete->count(),
            'check_time' => $this->checkTimeLabel($date),
            'show_alert' => $incomplete->isNotEmpty(),
        ];
    }

    /** @return array<string, mixed> */
    private function stats(Builder $query): array
    {
        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach (DeliveryOrderStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'total' => array_sum($byStatus),
            'assigned' => $byStatus[DeliveryOrderStatus::Assigned->value] ?? 0,
            'in_transit' => $byStatus[DeliveryOrderStatus::InTransit->value] ?? 0,
            'delivered' => $byStatus[DeliveryOrderStatus::Delivered->value] ?? 0,
            'failed' => $byStatus[DeliveryOrderStatus::Failed->value] ?? 0,
            'transferred' => $byStatus[DeliveryOrderStatus::Transferred->value] ?? 0,
            'reassigned' => $byStatus[DeliveryOrderStatus::Reassigned->value] ?? 0,
            'cancelled' => $byStatus[DeliveryOrderStatus::Cancelled->value] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function taskQuery(array $filters, Carbon $date): Builder
    {
        $query = DeliveryOrder::query()
            ->whereHas('jobSheet', fn (Builder $q) => $q->whereDate('operating_date', $date))
            ->with([
                'consignmentNote',
                'sourceBranch',
                'jobSheet.operatingBranch',
                'driver',
                'lorry',
            ]);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->where('source_branch_id', $filters['branch_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['job_sheet_id'] ?? null)) {
            $query->where('job_sheet_id', $filters['job_sheet_id']);
        }

        if (filled($filters['driver_id'] ?? null)) {
            $query->where('driver_id', $filters['driver_id']);
        }

        if (filled($filters['lorry_id'] ?? null)) {
            $query->where('lorry_id', $filters['lorry_id']);
        }

        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);

            $query->where(function (Builder $q) use ($needle): void {
                $q->where('number', 'like', '%'.$needle.'%')
                    ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'))
                    ->orWhereHas('jobSheet', fn (Builder $js) => $js->where('number', 'like', '%'.$needle.'%'));
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatTaskRow(DeliveryOrder $do, Carbon $date): array
    {
        $do->loadMissing(['consignmentNote', 'sourceBranch', 'jobSheet', 'driver', 'lorry']);
        $status = $do->status ?? DeliveryOrderStatus::Assigned;
        $csn = $do->consignmentNote;

        return [
            'id' => $do->id,
            'do_number' => $do->number,
            'view_url' => DeliveryTaskViewData::viewUrl($do),
            'job_sheet_number' => $do->jobSheet?->number,
            'branch' => $do->sourceBranch?->name ?? $do->jobSheet?->operatingBranch?->name,
            'driver' => $do->driver?->name,
            'lorry' => $do->lorry?->registration_no,
            'destination' => $this->formatDestination($csn?->delivery_city, $csn?->delivery_state),
            'delivery_date' => $do->jobSheet?->operating_date?->format('d/m/Y') ?? $date->format('d/m/Y'),
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            'status_color' => $status->getColor(),
        ];
    }

    private function formatDestination(?string $city, ?string $state): string
    {
        $parts = array_filter([$city, $state]);

        return $parts !== [] ? implode(', ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function incompleteTasks(array $filters, Carbon $date): Collection
    {
        $incompleteFilters = array_merge($filters, [
            'status' => null,
        ]);

        return $this->taskQuery($incompleteFilters, $date)
            ->whereNotIn('status', [
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Cancelled->value,
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeliveryOrder $do): array => $this->formatTaskRow($do, $date));
    }

    /** @return array<int, array<string, string>> */
    private function statusLegend(): array
    {
        return collect(DeliveryOrderStatus::cases())
            ->map(fn (DeliveryOrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->getLabel(),
                'color' => $status->getColor(),
            ])
            ->all();
    }

    private function checkTimeLabel(Carbon $date): string
    {
        if ($date->isToday() && now()->hour >= 16) {
            return now()->format('H:i');
        }

        return '16:15';
    }
}
