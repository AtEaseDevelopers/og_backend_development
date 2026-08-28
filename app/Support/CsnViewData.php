<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\JobSheetResource;
use App\Filament\Resources\SubsheetResource;

class CsnViewData
{
    public function for(ConsignmentNote $csn): array
    {
        $csn->loadMissing([
            'company',
            'customer',
            'quotation',
            'quotationDestination',
            'fromLocation',
            'toLocation',
            'lines',
            'deliveryOrder.driver',
            'deliveryOrder.lorry',
            'deliveryOrder.jobSheet',
            'deliveryOrders.lorry',
            'deliveryOrders.driver',
            'deliveryOrders.jobSheet',
            'sourceBranch',
            'subsheets.mainDriver',
            'subsheets.subDriver',
            'subsheets.mainLorry',
            'subsheets.subLorry',
            'subsheets.deliveryOrder',
            'subsheets.jobSheet',
        ]);

        $matrixState = app(CsnTransportMatrix::class)->toFormState($csn);
        $chargeColumn = $csn->delivery_city ?: $csn->consignee_name ?: ($matrixState['matrix_columns'][0] ?? null);

        return [
            'csn' => $csn,
            'overview' => $this->overview($csn),
            'consignor' => $this->consignor($csn),
            'consignee' => $this->consignee($csn),
            'charges' => $this->charges($csn, $matrixState, $chargeColumn),
            'rates' => app(CsnTransportMatrix::class)->preview(
                $matrixState['matrix_columns'],
                $matrixState['matrix_rows'],
            ),
            'charge_column' => $chargeColumn,
            'document' => $this->documentPreview($csn, $matrixState, $chargeColumn),
            'subsheets' => $this->subsheets($csn),
            'task' => $this->consignmentTask($csn),
        ];
    }

    /** @return array<string, mixed> */
    private function overview(ConsignmentNote $csn): array
    {
        return [
            'quotation' => $csn->quotation?->number,
            'destination' => $csn->quotationDestination?->city
                ?: $csn->quotationDestination?->consignee_name
                ?: $csn->delivery_city,
            'number' => $csn->number,
            'issued_at' => $csn->issued_at?->format('d/m/Y'),
            'do_number' => $csn->do_number ?: $csn->deliveryOrder?->number,
            'job_no' => $csn->job_no,
            'customer_reference' => $csn->customer_reference,
            'job_date' => $csn->job_date?->format('d/m/Y'),
            'from_area' => $csn->fromLocation?->name,
            'to_area' => $csn->toLocation?->name,
            'customer' => $csn->customer
                ? trim(($csn->customer->code ? $csn->customer->code.' — ' : '').$csn->customer->company_name)
                : null,
            'customer_phone' => $csn->customer_phone ?: $csn->customer?->phone,
            'tax_code' => $csn->tax_code,
            'customer_name' => $csn->customer_name ?: $csn->customer?->company_name,
            'tax_description' => $csn->tax_code_name,
            'customer_address' => $csn->customer?->address,
            'remarks' => $csn->remarks,
        ];
    }

    /** @return array<string, mixed> */
    private function consignor(ConsignmentNote $csn): array
    {
        return [
            'name' => $csn->consignor_name ?: $csn->customer_name,
            'address' => $csn->consignor_address,
            'phone' => $csn->consignor_phone,
        ];
    }

    /** @return array<string, mixed> */
    private function consignee(ConsignmentNote $csn): array
    {
        return [
            'name' => $csn->consignee_name,
            'pic' => $csn->consignee_pic,
            'address' => $csn->delivery_address,
            'phone' => $csn->consignee_phone,
            'postcode' => $csn->delivery_postcode,
            'city' => $csn->delivery_city,
            'state' => $csn->delivery_state,
        ];
    }

    /**
     * @param  array<string, mixed>  $matrixState
     * @return array<string, mixed>
     */
    private function charges(ConsignmentNote $csn, array $matrixState, ?string $chargeColumn): array
    {
        return [
            'transport_charges' => (float) $csn->transport_charges,
            'discount' => (float) $csn->discount,
            'subtotal' => (float) $csn->subtotal,
            'total_amount' => (float) $csn->total_amount,
            'tax_rate' => (float) $csn->tax_rate,
            'tax_amount' => (float) $csn->tax_amount,
            'advance_taken' => (bool) $csn->advance_taken,
            'issue_invoice' => (bool) $csn->issue_invoice,
            'other_do_numbers' => $csn->other_do_numbers ?? [],
            'marking' => $csn->marking,
            'destinations' => $matrixState['matrix_columns'] ?? [],
            'charge_column' => $chargeColumn,
            'has_additional_task' => $csn->subsheets->isNotEmpty(),
        ];
    }

    /**
     * @param  array<string, mixed>  $matrixState
     * @return array<string, mixed>
     */
    private function documentPreview(ConsignmentNote $csn, array $matrixState, ?string $chargeColumn): array
    {
        return app(CsnDocumentData::class)->fromConsignmentNote($csn);
    }

    /** @return list<array<string, mixed>> */
    private function subsheets(ConsignmentNote $csn): array
    {
        return $csn->subsheets
            ->map(fn ($subsheet) => [
                'number' => $subsheet->number,
                'do_number' => $subsheet->deliveryOrder?->number,
                'sub_lorry' => $subsheet->subLorry?->registration_no,
                'sub_driver' => $subsheet->subDriver?->name,
                'segment_route' => $subsheet->segment_route,
                'handover_status' => $subsheet->handover_status,
                'psi_amount' => (float) $subsheet->psi_amount,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function consignmentTask(ConsignmentNote $csn): array
    {
        $mainDo = $csn->deliveryOrder;
        $firstSubsheet = $csn->subsheets->first();

        $mainRoute = collect([
            $csn->fromLocation?->name,
            $csn->toLocation?->name,
        ])->filter()->implode(' → ');

        $segments = [];
        $segmentIndex = 0;

        if ($mainDo) {
            $segmentIndex++;
            $segments[] = [
                'index' => $segmentIndex,
                'type' => 'main',
                'label' => 'Main Delivery Segment',
                'driver' => $mainDo->driver?->name,
                'lorry' => $mainDo->lorry?->registration_no,
                'route' => $mainRoute ?: '—',
                'url' => $this->traceabilityUrl(DeliveryOrderResource::class, $mainDo, $csn),
            ];
        }

        foreach ($csn->subsheets as $index => $subsheet) {
            $segmentIndex++;
            $segments[] = [
                'index' => $segmentIndex,
                'type' => 'sub',
                'label' => 'Sub Segment '.($index + 1),
                'driver' => $subsheet->subDriver?->name,
                'lorry' => $subsheet->subLorry?->registration_no,
                'route' => $subsheet->segment_route ?: '—',
                'url' => $this->traceabilityUrl(SubsheetResource::class, $subsheet, $csn),
            ];
        }

        if ($segments !== []) {
            foreach ($segments as $i => &$segment) {
                $segment['active'] = $i === array_key_last($segments);
            }
            unset($segment);
        }

        $segmentActions = [
            [
                'label' => 'Add Subsheet',
                'action' => 'addSubsheets',
                'visible' => (bool) $csn->deliveryOrder?->job_sheet_id
                    && $csn->status !== CsnStatus::Cancelled,
            ],
            [
                'label' => 'Assign Driver',
                'action' => 'assignLorry',
                'visible' => ! $csn->deliveryOrder()->exists()
                    && $csn->status !== CsnStatus::Cancelled
                    && $csn->canAssignToLorry(),
            ],
        ];

        $commissions = [];

        if ($mainDo) {
            $subTotal = $csn->subsheets->sum(fn ($s) => (float) $s->psi_amount);
            $mainAmount = max(0, (float) $csn->transport_charges - $subTotal);
            $commissions[] = $this->commissionRow(
                'Main Delivery Segment',
                $mainDo->driver?->name,
                $mainDo->lorry?->registration_no,
                $mainRoute ?: '—',
                $mainAmount,
                (float) $csn->transport_charges,
            );
        }

        foreach ($csn->subsheets as $index => $subsheet) {
            $commissions[] = $this->commissionRow(
                'Sub Segment '.($index + 1),
                $subsheet->subDriver?->name,
                $subsheet->subLorry?->registration_no,
                $subsheet->segment_route ?: '—',
                (float) $subsheet->psi_amount,
                (float) $csn->transport_charges,
            );
        }

        $traceability = array_values(array_filter([
            [
                'label' => 'Original CSN',
                'value' => $csn->number,
                'url' => $this->traceabilityUrl(ConsignmentNoteResource::class, $csn, $csn),
            ],
            $mainDo?->jobSheet
                ? [
                    'label' => 'Original Job Sheet',
                    'value' => $mainDo->jobSheet->number,
                    'url' => $this->traceabilityUrl(JobSheetResource::class, $mainDo->jobSheet, $csn),
                ]
                : null,
            ...$csn->subsheets->map(fn ($s) => [
                'label' => 'Subsheet',
                'value' => $s->number,
                'url' => $this->traceabilityUrl(SubsheetResource::class, $s, $csn),
            ])->all(),
            ...$csn->deliveryOrders
                ->sortBy(fn ($do) => $do->parent_do_id ? 1 : 0)
                ->map(fn ($do) => [
                    'label' => 'Related DO',
                    'value' => $do->number,
                    'url' => $this->traceabilityUrl(DeliveryOrderResource::class, $do, $csn),
                ])->all(),
        ]));

        if ($traceability !== []) {
            foreach ($traceability as $index => &$item) {
                $item['active'] = $index === array_key_last($traceability);
            }
            unset($item);
        }

        $hasSubsheets = $csn->subsheets->isNotEmpty();
        $traceabilityDescription = $hasSubsheets
            ? 'This consignment remains linked to the original CSN, job sheet, subsheets and delivery orders.'
            : 'This consignment remains linked to the original CSN, job sheet and delivery orders.';

        return [
            'number' => $csn->number,
            'created_date' => $csn->issued_at?->format('d/m/Y') ?: $csn->created_at?->format('d/m/Y'),
            'transfer_code' => $firstSubsheet?->transfer_code,
            'transfer_branch' => $csn->sourceBranch?->name,
            'segment_count' => count($segments),
            'main_driver' => $mainDo?->driver?->name,
            'sub_driver' => $firstSubsheet?->subDriver?->name,
            'traceability' => $traceability,
            'traceability_description' => $traceabilityDescription,
            'segment_actions' => $segmentActions,
            'segments' => $segments,
            'commissions' => $commissions,
        ];
    }

    /** @param  class-string  $resourceClass */
    private function traceabilityUrl(string $resourceClass, mixed $record, ConsignmentNote $csn): string
    {
        return $resourceClass::getUrl('view', ['record' => $record], true, null, $csn->company);
    }

    /** @return array<string, mixed> */
    private function commissionRow(
        string $segment,
        ?string $driver,
        ?string $lorry,
        string $route,
        float $amount,
        float $transportTotal,
    ): array {
        return [
            'segment' => $segment,
            'driver' => $driver ?? '—',
            'lorry' => $lorry ?? '—',
            'route' => $route,
            'commission_pct' => $transportTotal > 0 && $amount > 0
                ? round(($amount / $transportTotal) * 100)
                : null,
            'amount' => $amount,
        ];
    }
}
