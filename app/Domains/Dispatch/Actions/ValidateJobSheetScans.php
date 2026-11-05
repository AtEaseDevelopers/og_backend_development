<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\DriverScanValidation;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Support\Collection;

/**
 * Section O items 16–21: the driver scans the CSN / DO barcodes of the job sheet before starting delivery.
 * A code is matched (a consignment of this job sheet), extra (a consignment of another job sheet / not yet
 * assigned) or unauthorized (no consignment matches, or a cancelled one). Expected consignments never scanned
 * are missing. An incomplete validation is recorded and the driver may still continue.
 */
class ValidateJobSheetScans
{
    /** The consignments the driver is expected to carry on the job sheet. */
    public function expected(JobSheet $jobSheet): Collection
    {
        return $jobSheet->deliveryOrders()
            ->with('consignmentNote')
            ->whereNotIn('status', [DeliveryOrderStatus::Cancelled->value, DeliveryOrderStatus::Transferred->value])
            ->get()
            ->map(fn (DeliveryOrder $do) => [
                'delivery_order_id' => $do->id,
                'do_number' => $do->number,
                'csn_number' => $do->consignmentNote?->number,
                'codes' => array_values(array_filter([
                    $do->number,
                    $do->consignmentNote?->number,
                    $do->consignmentNote?->qr_token,
                    $do->consignmentNote?->customer_do_number,
                ])),
            ])
            ->values();
    }

    /** @return array{result: string, code: string, delivery_order_id: ?int, label: string, message: string} */
    public function classify(JobSheet $jobSheet, string $code, ?Collection $expected = null): array
    {
        $code = trim($code);
        $needle = mb_strtolower($code);
        $expected ??= $this->expected($jobSheet);

        $match = $expected->first(fn (array $item) => in_array($needle, array_map('mb_strtolower', $item['codes']), true));

        if ($match) {
            return [
                'result' => 'matched',
                'code' => $code,
                'delivery_order_id' => $match['delivery_order_id'],
                'label' => trim(($match['csn_number'] ?? '').' / '.$match['do_number'], ' /'),
                'message' => 'Consignment belongs to this job sheet.',
            ];
        }

        $csn = ConsignmentNote::query()
            ->where('number', $code)
            ->orWhere('qr_token', $code)
            ->first();

        $do = $csn ? null : DeliveryOrder::query()->with('consignmentNote')->where('number', $code)->first();
        $csn ??= $do?->consignmentNote;

        if (! $csn || $csn->status === CsnStatus::Cancelled || $do?->status === DeliveryOrderStatus::Cancelled) {
            return [
                'result' => 'unauthorized',
                'code' => $code,
                'delivery_order_id' => null,
                'label' => $csn?->number ?? $code,
                'message' => $csn ? 'This consignment is cancelled. Do not load it.' : 'Unrecognised barcode. Do not load this item.',
            ];
        }

        return [
            'result' => 'extra',
            'code' => $code,
            'delivery_order_id' => null,
            'label' => trim($csn->number.($do ? ' / '.$do->number : ''), ' /'),
            'message' => 'This consignment is not assigned to this job sheet.',
        ];
    }

    public function record(JobSheet $jobSheet, ?Driver $driver, array $data): DriverScanValidation
    {
        if (! empty($data['client_uuid'])) {
            $existing = DriverScanValidation::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $expected = $this->expected($jobSheet);
        $codes = collect($data['scanned_codes'] ?? [])->map(fn ($c) => trim((string) $c))->filter()->unique()->values();
        $results = $codes->map(fn (string $code) => $this->classify($jobSheet, $code, $expected));

        $matchedIds = $results->where('result', 'matched')->pluck('delivery_order_id')->unique();
        $missing = $expected->whereNotIn('delivery_order_id', $matchedIds->all())
            ->map(fn (array $item) => ['delivery_order_id' => $item['delivery_order_id'], 'do_number' => $item['do_number'], 'csn_number' => $item['csn_number']])
            ->values();
        $extra = $results->where('result', 'extra')->values();
        $unauthorized = $results->where('result', 'unauthorized')->values();
        $complete = $missing->isEmpty() && $extra->isEmpty() && $unauthorized->isEmpty();

        return DriverScanValidation::query()->create([
            'job_sheet_id' => $jobSheet->id,
            'driver_id' => $driver?->id,
            'expected' => $expected->map(fn (array $item) => collect($item)->except('codes')->all())->all(),
            'scanned' => $results->all(),
            'missing' => $missing->all(),
            'extra' => $extra->all(),
            'unauthorized' => $unauthorized->all(),
            'is_complete' => $complete,
            'continued_incomplete' => ! $complete && (bool) ($data['continue_incomplete'] ?? true),
            'remarks' => $data['remarks'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
            'validated_at' => $data['validated_at'] ?? now(),
        ]);
    }
}
