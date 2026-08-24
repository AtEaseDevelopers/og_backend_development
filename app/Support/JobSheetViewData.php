<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\JobSheetStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\JobSheetResource;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

class JobSheetViewData
{
    /** @return array<string, mixed> */
    public function for(JobSheet $jobSheet): array
    {
        $jobSheet->loadMissing([
            'operatingBranch',
            'lorry.defaultDriver',
            'driver',
            'tasks.deliveryOrder.consignmentNote.customer',
            'tasks.deliveryOrder.lorry',
            'tasks.deliveryOrder.driver',
            'deliveryOrders.consignmentNote.customer',
            'deliveryOrders.lorry',
            'deliveryOrders.driver',
            'subsheets.consignmentNote',
            'subsheets.deliveryOrder.consignmentNote.customer',
            'subsheets.deliveryOrder.lorry',
            'subsheets.deliveryOrder.driver',
        ]);

        $summary = $this->summaryCounts($jobSheet);
        $status = $jobSheet->status ?? JobSheetStatus::Draft;

        return [
            'job_sheet' => $jobSheet,
            'header' => [
                'number' => $jobSheet->number,
                'status' => $status->value,
                'status_label' => $status->getLabel(),
                'status_color' => $status->getColor(),
            ],
            'stepper' => $this->stepper($status),
            'tracking' => $this->tracking($jobSheet, $status),
            'information' => [
                'number' => $jobSheet->number,
                'operating_date' => $jobSheet->operating_date?->format('d/m/Y'),
                'operating_branch' => $jobSheet->operatingBranch?->name,
                'status_label' => $status->getLabel(),
                'status_color' => $status->getColor(),
            ],
            'assignment' => [
                'lorry_number' => $jobSheet->lorry?->registration_no,
                'default_driver' => $jobSheet->lorry?->defaultDriver?->name,
                'current_driver' => $jobSheet->driver?->name,
                'current_driver_id' => $jobSheet->driver_id,
                'lorry_id' => $jobSheet->lorry_id,
                'editable' => $status === JobSheetStatus::Draft,
            ],
            'summary' => $summary,
            'grouped_tasks' => $this->groupedTasks($jobSheet),
            'driver_options' => $this->driverOptions($jobSheet),
            'lorry_options' => $this->lorryOptions($jobSheet),
            'list_url' => Filament::getTenant()
                ? JobSheetResource::getUrl('index', [], true, null, Filament::getTenant())
                : JobSheetResource::getUrl('index'),
        ];
    }

    /** @return array<string, mixed> */
    private function stepper(JobSheetStatus $status): array
    {
        $steps = [
            ['key' => 'draft', 'label' => 'Draft'],
            ['key' => 'in_transit', 'label' => 'In Transit'],
            ['key' => 'completed', 'label' => 'Completed'],
        ];

        $activeIndex = match ($status) {
            JobSheetStatus::Draft => 0,
            JobSheetStatus::InTransit => 1,
            JobSheetStatus::Completed => 2,
        };

        return collect($steps)->map(function (array $step, int $index) use ($activeIndex): array {
            return $step + [
                'state' => match (true) {
                    $index < $activeIndex => 'done',
                    $index === $activeIndex => 'active',
                    default => 'upcoming',
                },
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function tracking(JobSheet $jobSheet, JobSheetStatus $status): array
    {
        $checkedIn = filled($jobSheet->checked_in_at);

        return [
            'driver_check_in' => $checkedIn ? 'Completed' : 'Pending',
            'driver_check_in_done' => $checkedIn,
            'journey_commencement' => $checkedIn || $status !== JobSheetStatus::Draft ? 'Recorded' : 'Pending',
            'journey_commencement_done' => $checkedIn || $status !== JobSheetStatus::Draft,
            'in_transit_start_date' => $jobSheet->checked_in_at?->format('d/m/Y') ?? '—',
            'in_transit_start_time' => $jobSheet->checked_in_at?->format('H:i') ?? '—',
        ];
    }

    /** @return array<string, int> */
    private function summaryCounts(JobSheet $jobSheet): array
    {
        $routeGroups = $jobSheet->tasks
            ->pluck('route_group')
            ->filter()
            ->unique()
            ->count();

        if ($routeGroups === 0 && $jobSheet->deliveryOrders->isNotEmpty()) {
            $routeGroups = 1;
        }

        return [
            'route_groups' => $routeGroups,
            'subsheets' => $jobSheet->subsheets->count(),
            'csns' => $jobSheet->deliveryOrders
                ->pluck('consignment_note_id')
                ->filter()
                ->unique()
                ->count(),
            'dos' => $jobSheet->deliveryOrders->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function groupedTasks(JobSheet $jobSheet): array
    {
        if ($jobSheet->subsheets->isNotEmpty()) {
            return $jobSheet->subsheets
                ->map(fn (Subsheet $subsheet): array => $this->subsheetGroup($subsheet))
                ->values()
                ->all();
        }

        $tasks = $jobSheet->tasks->sortBy('sequence');

        if ($tasks->isEmpty()) {
            return $jobSheet->deliveryOrders
                ->map(fn (DeliveryOrder $deliveryOrder): array => $this->deliveryOrderGroup(
                    $deliveryOrder->consignmentNote?->delivery_state ?? 'MAIN',
                    collect([$deliveryOrder]),
                    strtoupper($deliveryOrder->number),
                    'DELIVERY',
                ))
                ->values()
                ->all();
        }

        return $tasks
            ->groupBy(fn ($task) => $task->route_group ?: 'default')
            ->map(function (Collection $items, string $routeGroup): array {
                /** @var Collection<int, DeliveryOrder> $deliveryOrders */
                $deliveryOrders = $items
                    ->map(fn ($task) => $task->deliveryOrder)
                    ->filter()
                    ->unique('id')
                    ->values();

                $firstCsn = $deliveryOrders->first()?->consignmentNote;
                $code = $routeGroup === 'default'
                    ? 'MAIN-'.str_pad((string) ($items->first()?->id ?? 0), 3, '0', STR_PAD_LEFT)
                    : strtoupper(substr(preg_replace('/\s+/', '', $routeGroup) ?? 'RG', 0, 6)).'-'.str_pad((string) ($items->first()?->id ?? 0), 2, '0', STR_PAD_LEFT);

                return $this->deliveryOrderGroup(
                    $firstCsn?->delivery_state ?? strtoupper($routeGroup),
                    $deliveryOrders,
                    $code,
                    'DELIVERY',
                    $firstCsn?->delivery_postcode,
                );
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function subsheetGroup(Subsheet $subsheet): array
    {
        $deliveryOrder = $subsheet->deliveryOrder;
        $consignmentNote = $subsheet->consignmentNote ?? $deliveryOrder?->consignmentNote;
        $deliveryOrders = collect([$deliveryOrder])->filter();

        $type = str_contains(strtolower((string) $subsheet->task_type), 'transfer')
            ? 'TRANSFER'
            : 'DELIVERY';

        return $this->deliveryOrderGroup(
            $consignmentNote?->delivery_state ?? '—',
            $deliveryOrders,
            $subsheet->number,
            $type,
            $consignmentNote?->delivery_postcode,
            $subsheet->segment_route ?: $subsheet->transfer_code,
        );
    }

    /**
     * @param  Collection<int, DeliveryOrder>  $deliveryOrders
     * @return array<string, mixed>
     */
    private function deliveryOrderGroup(
        string $state,
        Collection $deliveryOrders,
        string $code,
        string $type,
        ?string $postcode = null,
        ?string $transferHint = null,
    ): array {
        $csnCount = $deliveryOrders->pluck('consignment_note_id')->filter()->unique()->count();

        return [
            'code' => $code,
            'type' => $type,
            'state' => strtoupper($state),
            'postcode' => $postcode ?? '—',
            'csn_count' => $csnCount,
            'do_count' => $deliveryOrders->count(),
            'rows' => $deliveryOrders
                ->map(fn (DeliveryOrder $deliveryOrder): array => $this->taskRow($deliveryOrder, $transferHint))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function taskRow(DeliveryOrder $deliveryOrder, ?string $transferHint = null): array
    {
        $consignmentNote = $deliveryOrder->consignmentNote;
        $tenant = Filament::getTenant();

        return [
            'csn_number' => $consignmentNote?->number,
            'do_number' => $deliveryOrder->number,
            'customer' => $consignmentNote?->customer?->company_name
                ?? $consignmentNote?->customer_name,
            'destination' => $consignmentNote?->delivery_city
                ?: $consignmentNote?->delivery_address,
            'transfer_allocation' => $transferHint ?: '—',
            'driver_lorry' => trim(sprintf(
                '%s / %s',
                $deliveryOrder->driver?->name ?? '—',
                $deliveryOrder->lorry?->registration_no ?? '—',
            ), ' /'),
            'csn_url' => ($tenant && $consignmentNote)
                ? ConsignmentNoteResource::getUrl('view', ['record' => $consignmentNote], true, null, $tenant)
                : null,
            'do_url' => $tenant
                ? DeliveryOrderResource::getUrl('view', ['record' => $deliveryOrder], true, null, $tenant)
                : null,
        ];
    }

    /** @return array<int|string, string> */
    private function driverOptions(JobSheet $jobSheet): array
    {
        $query = Driver::query()->where('is_active', true)->orderBy('name');

        if ($companyId = $jobSheet->company_id) {
            $query->where(function ($builder) use ($companyId): void {
                $builder->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    private function lorryOptions(JobSheet $jobSheet): array
    {
        $query = Lorry::query()->where('is_active', true)->orderBy('registration_no');

        if ($companyId = $jobSheet->company_id) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('registration_no', 'id')->all();
    }
}
