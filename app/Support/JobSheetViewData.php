<?php

namespace App\Support;

use App\Domains\Delivery\Models\DriverTaskProof;
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
            'tasks.deliveryOrder.subsheet',
            'deliveryOrders.consignmentNote.customer',
            'deliveryOrders.lorry',
            'deliveryOrders.driver',
            'subsheets.consignmentNote',
            'subsheets.deliveryOrder.consignmentNote.customer',
            'subsheets.deliveryOrder.lorry',
            'subsheets.deliveryOrder.driver',
            'subsheets.deliveryOrder.subsheet',
            'parent.lorry',
            'transferJobSheets.lorry',
            'transferJobSheets.driver',
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
            // transfer job sheets (client's BA, 3 Nov 2026): where this sheet came from, or the ones opened from it
            'transfer' => [
                'is_transfer' => $jobSheet->isTransfer(),
                'unassigned' => $jobSheet->isUnassigned(),
                'code' => $jobSheet->transfer_code,
                'origin' => $jobSheet->parent ? [
                    'number' => $jobSheet->parent->number,
                    'lorry' => $jobSheet->parent->lorry?->registration_no,
                    'url' => JobSheetResource::getUrl('view', ['record' => $jobSheet->parent]),
                ] : null,
                'sheets' => $jobSheet->transferJobSheets->map(fn (JobSheet $sheet) => [
                    'number' => $sheet->number,
                    'code' => $sheet->transfer_code,
                    'legs' => $sheet->subsheets()->count(),
                    'lorry' => $sheet->lorry?->registration_no,
                    'driver' => $sheet->driver?->name,
                    'status' => $sheet->isUnassigned() ? 'Unassigned' : ($sheet->status?->getLabel() ?? ''),
                    'url' => JobSheetResource::getUrl('view', ['record' => $sheet]),
                ])->values()->all(),
            ],
            'grouped_tasks' => $this->groupedTasks($jobSheet),
            'driver_app' => $this->driverAppRecords($jobSheet),
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

    /** Driver app records of the trip (section O): barcode validations and pickup / handover proofs. */
    private function driverAppRecords(JobSheet $jobSheet): array
    {
        $codes = fn (?array $items) => collect($items ?? [])
            ->map(fn ($item) => $item['label'] ?? $item['do_number'] ?? $item['code'] ?? null)
            ->filter()
            ->implode(', ');

        $validations = $jobSheet->scanValidations()->with('driver')->latest('id')->get()
            ->map(fn ($v) => [
                'at' => $v->validated_at?->format('d/m/Y H:i'),
                'driver' => $v->driver?->name,
                'result' => $v->is_complete ? 'Complete' : ($v->continued_incomplete ? 'Incomplete — driver continued' : 'Incomplete'),
                'complete' => $v->is_complete,
                'scanned' => collect($v->scanned ?? [])->where('result', 'matched')->count().' / '.count($v->expected ?? []).' matched',
                'missing' => $codes($v->missing),
                'extra' => $codes($v->extra),
                'unauthorized' => $codes($v->unauthorized),
            ])->all();

        $proofs = DriverTaskProof::query()
            ->with(['deliveryOrder', 'driver'])
            ->whereIn('delivery_order_id', $jobSheet->deliveryOrders->pluck('id'))
            ->latest('recorded_at')
            ->get()
            ->map(fn ($p) => [
                'at' => $p->recorded_at?->format('d/m/Y H:i'),
                'type' => ucfirst($p->type),
                'do_number' => $p->deliveryOrder?->number,
                'driver' => $p->driver?->name,
                'handover_to' => $p->handover_to,
                'gps' => $p->latitude !== null ? $p->latitude.', '.$p->longitude : null,
                'photos' => collect($p->photo_paths ?? [])->map(fn ($path) => DriverTaskPayload::url($path))->all(),
                'remarks' => $p->remarks,
            ])->all();

        return ['validations' => $validations, 'proofs' => $proofs];
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
        // a trip can carry subsheet legs (one group each) and its own CSN deliveries (grouped by route)
        $subsheetGroups = $jobSheet->subsheets
            ->map(fn (Subsheet $subsheet): array => $subsheet->delivery_order_id ? $this->subsheetGroup($subsheet) : $this->waitingSubsheetGroup($subsheet))
            ->values()
            ->all();
        $subsheetDoIds = $jobSheet->subsheets->pluck('delivery_order_id')->filter()->map(fn ($id) => (int) $id)->all();
        $isOwn = fn (?DeliveryOrder $deliveryOrder): bool => $deliveryOrder !== null
            && ! in_array((int) $deliveryOrder->id, $subsheetDoIds, true)
            && $deliveryOrder->subsheet_id === null;

        return array_merge($subsheetGroups, $this->ownDeliveryGroups($jobSheet, $isOwn));
    }

    /**
     * The trip's own CSN deliveries (not subsheet legs), grouped by route.
     *
     * @param  callable(?DeliveryOrder): bool  $isOwn
     * @return list<array<string, mixed>>
     */
    private function ownDeliveryGroups(JobSheet $jobSheet, callable $isOwn): array
    {
        $tasks = $jobSheet->tasks->sortBy('sequence')->filter(fn ($task) => $isOwn($task->deliveryOrder));

        if ($tasks->isEmpty()) {
            return $jobSheet->deliveryOrders
                ->filter(fn (DeliveryOrder $deliveryOrder) => $isOwn($deliveryOrder))
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

        // PICKUP / TRANSFER / BREAK BULK (the subsheet's type)
        $type = strtoupper($subsheet->typeLabel());

        return $this->deliveryOrderGroup(
            $consignmentNote?->delivery_state ?? '—',
            $deliveryOrders,
            $subsheet->number,
            $type,
            $consignmentNote?->delivery_postcode,
            $subsheet->segment_route ?: $subsheet->transfer_code,
        );
    }

    /** A leg on a transfer job sheet nobody has picked up yet: no delivery order, the CSN and its code only. */
    private function waitingSubsheetGroup(Subsheet $subsheet): array
    {
        $csn = $subsheet->consignmentNote;

        return [
            'code' => $subsheet->number,
            'type' => strtoupper($subsheet->typeLabel()),
            'state' => strtoupper((string) ($csn?->delivery_state ?: '—')),
            'postcode' => $csn?->delivery_postcode ?? '—',
            'csn_count' => $csn ? 1 : 0,
            'do_count' => 0,
            'rows' => [[
                'csn_number' => $csn?->number,
                'do_number' => 'Waiting · no lorry yet',
                'task_type' => ['key' => (string) $subsheet->task_type, 'label' => $subsheet->typeLabel()],
                'customer' => $csn?->customer_name,
                'destination' => $csn?->delivery_city ?: $csn?->delivery_state,
                'transfer_allocation' => $subsheet->transfer_code,
                'driver_lorry' => null,
                'do_url' => null,
            ]],
        ];
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

    /**
     * What the lorry does for this delivery order: its own CSN delivery, a subsheet leg (Subsheet = pick up and
     * bring to the hub, Transfer = hand over to another lorry, Break bulk = from the driver app), or a re-delivery
     * of a failed one.
     *
     * @return array{key: string, label: string}
     */
    private function taskType(DeliveryOrder $deliveryOrder): array
    {
        $subsheet = $deliveryOrder->subsheet
            ?? ($deliveryOrder->parent_do_id ? Subsheet::query()->where('delivery_order_id', $deliveryOrder->id)->first() : null);

        if ($subsheet) {
            return ['key' => (string) $subsheet->task_type, 'label' => $subsheet->typeLabel()];
        }

        return $deliveryOrder->parent_do_id
            ? ['key' => 'redelivery', 'label' => 'Re-delivery']
            : ['key' => 'delivery', 'label' => 'Delivery'];
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
            'task_type' => $this->taskType($deliveryOrder),
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
