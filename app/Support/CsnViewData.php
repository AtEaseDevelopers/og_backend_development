<?php

namespace App\Support;

use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Commission\Actions\ResolveCommissionShares;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
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
            // (the Rates and Document preview cards were removed from the page, 13 Oct 2026)
            'subsheets' => $this->subsheets($csn),
            'task' => $this->consignmentTask($csn),
        ];
    }

    /**
     * The CSN overview: what was entered when the order was created (the CSN's own copy, else its order record's —
     * a CSN created before a field was carried shows the order's value).
     *
     * @return array<string, mixed>
     */
    private function overview(ConsignmentNote $csn): array
    {
        $order = $csn->quotation;

        return [
            'quotation' => $csn->quotation?->number,
            'destination' => $csn->quotationDestination?->city
                ?: $csn->quotationDestination?->consignee_name
                ?: $csn->delivery_city,
            'number' => $csn->number,
            'issued_at' => $csn->issued_at?->format('d/m/Y'),
            'do_number' => $csn->do_number ?: $csn->deliveryOrder?->number,
            // the customer's own DO number (the order's "DO number"); the system DO number is under Traceability
            'customer_do_number' => $csn->customer_do_number ?: $order?->customer_do_number,
            'job_no' => $csn->job_no,
            'customer_reference' => $csn->customer_reference,
            'job_date' => $csn->job_date?->format('d/m/Y'),
            'from_area' => $csn->fromLocation?->name ?? $order?->fromLocation?->name,
            'to_area' => $csn->toLocation?->name ?? $order?->toLocation?->name,
            'customer' => $csn->customer
                ? trim(($csn->customer->code ? $csn->customer->code.' — ' : '').$csn->customer->company_name)
                : null,
            // the order's contact number (section 1), else the CSN's / the customer's
            'customer_phone' => $order?->customer_pic_phone ?: ($csn->customer_phone ?: $csn->customer?->phone),
            'tax_code' => $csn->tax_code,
            'customer_name' => $csn->customer_name ?: $csn->customer?->company_name,
            'tax_description' => $csn->tax_code_name,
            // the order's billing address, else the customer's
            'customer_address' => $order?->customer_address ?: $csn->customer?->address,
            // the consignor & consignee's instructions on the order
            'remarks' => $csn->remarks ?: (UpdateOrderRecords::instructionsFromNotes($order?->notes) ?: null),
        ];
    }

    /** @return array<string, mixed> */
    private function consignor(ConsignmentNote $csn): array
    {
        return [
            // '' = left blank on the order (shown as "—"); not set at all (older CSNs) = the customer
            'name' => $csn->consignor_name === '' ? null : ($csn->consignor_name ?: $csn->customer_name),
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


    /** @return list<array<string, mixed>> */
    private function subsheets(ConsignmentNote $csn): array
    {
        return $csn->subsheets
            ->map(fn ($subsheet) => [
                'number' => $subsheet->number,
                'url' => $this->traceabilityUrl(SubsheetResource::class, $subsheet, $csn),
                'type' => $subsheet->typeLabel(),
                'type_key' => (string) $subsheet->task_type,
                'transfer_code' => $subsheet->transfer_code,
                'do_number' => $subsheet->deliveryOrder?->number,
                'do_status' => $subsheet->deliveryOrder?->status instanceof \BackedEnum
                    ? ucfirst(str_replace('_', ' ', $subsheet->deliveryOrder->status->value))
                    : null,
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
                'type' => null,
                'transfer_code' => null,
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
                'lorry' => $subsheet->subLorry?->registration_no ?? 'No lorry yet',
                'route' => $subsheet->segment_route ?: null,
                'type' => $subsheet->typeLabel(),
                'type_key' => (string) $subsheet->task_type,
                'transfer_code' => $subsheet->transfer_code,
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
                // no lorry needed on the CSN first (a subsheet can be created and assigned later)
                'visible' => $csn->status !== CsnStatus::Cancelled,
            ],
            [
                'label' => 'Assign Driver',
                'action' => 'assignLorry',
                'visible' => ! $csn->deliveryOrder()->exists()
                    && $csn->status !== CsnStatus::Cancelled
                    && $csn->canAssignToLorry(),
            ],
        ];

        // what each assigned segment's driver earns under the commission rules (the same calculation as the
        // month-end commission slips: rate % of the CSN total, shared between the CSN's drivers by the rule)
        $commissions = [];

        if ($mainDo) {
            $commissions[] = $this->commissionRow(
                'Main Delivery Segment',
                $mainDo->driver?->name,
                $mainDo->lorry?->registration_no,
                $mainRoute ?: '—',
                $mainDo,
                null,
            );
        }

        foreach ($csn->subsheets as $index => $subsheet) {
            $commissions[] = $this->commissionRow(
                'Sub Segment '.($index + 1).' · '.$subsheet->typeLabel(),
                $subsheet->subDriver?->name,
                $subsheet->subLorry?->registration_no,
                $subsheet->segment_route ?: '—',
                $subsheet->deliveryOrder,
                $subsheet->transfer_code,
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
                ->sortBy(fn ($do) => $do->isSubDo() ? 1 : 0)
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
        ?DeliveryOrder $do,
        ?string $transferCode = null,
    ): array {
        // a segment without a driver yet earns nothing yet
        $assigned = $do !== null && $do->driver_id !== null;
        $shares = app(ResolveCommissionShares::class);
        $rate = $assigned ? $shares->rateFor($do) : null;
        $amount = $assigned
            ? round((float) $shares->execute($do)->where('driver_id', $do->driver_id)->sum('amount'), 2)
            : null;

        return [
            'segment' => $segment,
            'driver' => $driver ?? '—',
            'lorry' => $lorry ?? '—',
            // a transfer code belongs to a subsheet / break bulk leg only
            'transfer_code' => $transferCode ?: '—',
            'route' => $route,
            'commission_pct' => $rate !== null ? $this->percent($rate['rate']) : null,
            'rule' => $rate !== null ? ($rate['rule'] ?? 'Default rate') : null,
            // a Pickup driver's fixed amount, else this driver's equal part of what is left
            'share' => match (true) {
                $rate === null => null,
                $rate['pickup'] ?? false => 'Pickup · fixed amount',
                $rate['drivers'] > 1 => 'Shared by '.$rate['drivers'].' drivers',
                default => null,
            },
            'amount' => $amount,
        ];
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
