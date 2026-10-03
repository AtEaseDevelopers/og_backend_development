<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One line per lorry trip for an operating date: job sheet, lorry, driver, origin and the
 * de-duplicated destination list (e.g. "PUCHONG / KLANG"), grouped by operating branch.
 * Orders carried for another branch (shared dispatch) are listed separately per branch,
 * like the "O&G KL ORDER" block on the manual daily sheet.
 */
class DailyLorryScheduleData
{
    /**
     * @return array{
     *     date: Carbon,
     *     branches: list<array{code: string, name: string, trips: list<array<string, mixed>>, other_orders: list<array<string, mixed>>}>,
     *     total_trips: int,
     *     total_tasks: int
     * }
     */
    public function for(Carbon|string $date, ?int $branchId = null): array
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $sheets = JobSheet::query()
            ->with([
                'lorry.branch', 'driver', 'operatingBranch',
                'deliveryOrders.consignmentNote.fromLocation',
                'deliveryOrders.consignmentNote.sourceBranch',
                'deliveryOrders.consignmentNote.customer',
            ])
            ->whereDate('operating_date', $date->toDateString())
            ->when($branchId, fn ($q) => $q->where('operating_branch_id', $branchId))
            ->orderBy('operating_branch_id')
            ->orderBy('trip_no')
            ->orderBy('number')
            ->get();

        $branches = $sheets
            ->groupBy('operating_branch_id')
            ->map(function (Collection $branchSheets) {
                /** @var JobSheet $first */
                $first = $branchSheets->first();
                $branch = $first->operatingBranch;

                return [
                    'code' => $branch?->code ?? '—',
                    'name' => $branch?->name ?? 'Unknown branch',
                    'trips' => $branchSheets->map(fn (JobSheet $sheet) => $this->tripRow($sheet, $branch))->values()->all(),
                    'other_orders' => $this->otherBranchOrders($branchSheets, $branch),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();

        return [
            'date' => $date,
            'branches' => $branches,
            'total_trips' => $sheets->count(),
            'total_tasks' => $sheets->sum(fn (JobSheet $s) => $s->deliveryOrders->count()),
        ];
    }

    /** @return array<string, mixed> */
    private function tripRow(JobSheet $sheet, ?Branch $branch): array
    {
        $orders = $sheet->deliveryOrders;
        $csns = $orders->map->consignmentNote->filter();

        $origins = $csns->map(fn ($csn) => $csn->fromLocation?->name)->filter()->unique();
        $origin = $origins->isNotEmpty() ? $origins->implode(' / ') : ($branch?->code ?? '—');

        return [
            'id' => $sheet->id,
            'number' => $sheet->number,
            'trip_label' => $sheet->tripLabel(),
            'lorry' => $sheet->lorry?->registration_no ?? '—',
            'lorry_branch' => $sheet->lorry?->branch?->code,
            'shared' => (bool) $sheet->is_shared_dispatch,
            'driver' => $sheet->driver?->name,
            'origin' => strtoupper($origin),
            'destinations' => $this->destinationList($csns),
            'customers' => $csns->map(fn ($csn) => $csn->customer?->company_name ?? $csn->customer_name)->filter()->unique()->values()->all(),
            'task_count' => $orders->count(),
            'status' => $sheet->status,
            'status_label' => $sheet->status?->getLabel() ?? '—',
            'status_color' => $sheet->status?->getColor() ?? 'gray',
            'checked_in_at' => $sheet->checked_in_at?->format('H:i'),
        ];
    }

    /** @param  Collection<int, \App\Domains\Consignment\Models\ConsignmentNote>  $csns */
    private function destinationList(Collection $csns): string
    {
        $places = $csns
            ->map(fn ($csn) => $csn->delivery_city ?: $csn->delivery_state ?: $csn->consignee_name)
            ->filter()
            ->map(fn ($place) => strtoupper(trim((string) $place)))
            ->unique()
            ->values();

        return $places->isNotEmpty() ? $places->implode(' / ') : '—';
    }

    /**
     * Deliveries on this branch's lorries whose CSN belongs to another branch.
     *
     * @param  Collection<int, JobSheet>  $sheets
     * @return list<array<string, mixed>>
     */
    private function otherBranchOrders(Collection $sheets, ?Branch $branch): array
    {
        $rows = [];

        foreach ($sheets as $sheet) {
            foreach ($sheet->deliveryOrders as $do) {
                /** @var DeliveryOrder $do */
                $csn = $do->consignmentNote;

                if (! $csn || (int) $csn->source_branch_id === (int) $branch?->id) {
                    continue;
                }

                $rows[] = [
                    'source_branch' => $csn->sourceBranch?->code ?? '—',
                    'lorry' => $sheet->lorry?->registration_no ?? '—',
                    'origin' => strtoupper($csn->fromLocation?->name ?? $csn->sourceBranch?->code ?? '—'),
                    'destination' => strtoupper($csn->delivery_city ?: $csn->delivery_state ?: ($csn->consignee_name ?? '—')),
                    'customer' => $csn->customer?->company_name ?? $csn->customer_name,
                    'csn' => $csn->number,
                    'job_sheet' => $sheet->number,
                ];
            }
        }

        return collect($rows)->sortBy(['source_branch', 'lorry'])->values()->all();
    }
}
