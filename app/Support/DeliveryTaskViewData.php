<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use App\Filament\Pages\DeliveryMonitoring;
use App\Filament\Resources\DeliveryOrderResource;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

class DeliveryTaskViewData
{
    /** @return array<string, mixed> */
    public function for(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing([
            'consignmentNote',
            'sourceBranch',
            'jobSheet.operatingBranch',
            'driver',
            'lorry',
            'proofOfDelivery.driver',
            'failedDelivery.driver',
            'breakBulks',
        ]);

        $csn = $deliveryOrder->consignmentNote;
        $pod = $deliveryOrder->proofOfDelivery;
        $failed = $deliveryOrder->failedDelivery;
        $status = $deliveryOrder->status ?? DeliveryOrderStatus::Assigned;
        $destination = $this->formatDestination($csn?->delivery_city, $csn?->delivery_state, $csn?->delivery_address);

        return [
            'header' => [
                'do_number' => $deliveryOrder->number,
                'job_sheet_number' => $deliveryOrder->jobSheet?->number,
                'status_label' => $status->getLabel(),
                'status_color' => $status->getColor(),
            ],
            'overview' => [
                'branch' => $deliveryOrder->sourceBranch?->name ?? $deliveryOrder->jobSheet?->operatingBranch?->name,
                'driver' => $deliveryOrder->driver?->name,
                'lorry' => $deliveryOrder->lorry?->registration_no,
                'date' => $deliveryOrder->jobSheet?->operating_date?->format('d/m/Y'),
                'destination' => $destination,
            ],
            'photos' => $this->photos($pod?->photo_paths ?? $failed?->photo_paths),
            'photos_meta' => [
                'uploaded_by' => $pod?->driver?->name ?? $failed?->driver?->name ?? $deliveryOrder->driver?->name ?? 'Driver',
                'related_do' => $deliveryOrder->number,
            ],
            'documents' => $this->documents($deliveryOrder, $pod?->pod_document_path),
            'signature' => [
                'signee_name' => $pod?->recipient_name ?? $csn?->consignee_name,
                'signature_url' => $this->assetUrl($pod?->signature_path),
                'signed_at' => $pod?->delivered_at?->format('d/m/Y H:i'),
            ],
            'gps' => [
                'location' => $destination,
                'latitude' => $pod?->latitude ?? $failed?->latitude,
                'longitude' => $pod?->longitude ?? $failed?->longitude,
                'record_type' => $pod ? 'Delivery Location' : ($failed ? 'Failure Location' : '—'),
                'recorded_by' => 'Driver App',
                'related_do' => $deliveryOrder->number,
            ],
            'offline_sync' => [
                'offline_supported' => 'Yes',
                'local_record' => filled($pod?->client_uuid ?? $failed?->client_uuid) ? 'Recorded' : '—',
                'latest_sync' => ($pod?->synced_at ?? $failed?->synced_at)?->format('d/m/Y H:i'),
                'sync_result' => filled($pod?->synced_at ?? $failed?->synced_at) ? 'Synchronized' : 'Pending',
                'sync_success' => filled($pod?->synced_at ?? $failed?->synced_at),
            ],
            'timestamps' => $this->timestamps($deliveryOrder, $pod, $failed),
            'monitoring_url' => Filament::getTenant()
                ? DeliveryMonitoring::getUrl([], true, null, Filament::getTenant())
                : DeliveryMonitoring::getUrl(),
        ];
    }

    public static function viewUrl(DeliveryOrder $deliveryOrder): string
    {
        return Filament::getTenant()
            ? DeliveryOrderResource::getUrl('view', ['record' => $deliveryOrder], true, null, Filament::getTenant())
            : DeliveryOrderResource::getUrl('view', ['record' => $deliveryOrder]);
    }

    private function formatDestination(?string $city, ?string $state, ?string $address = null): string
    {
        $parts = array_filter([$city, $state]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return filled($address) ? (string) $address : '—';
    }

    /** @return array<int, array<string, mixed>> */
    private function photos(?array $paths): array
    {
        $labels = [
            'Goods at Delivery Location',
            'Goods Handover',
            'Delivery Confirmation',
        ];

        if (! is_array($paths) || $paths === []) {
            return collect($labels)->map(fn (string $label): array => [
                'label' => $label,
                'url' => null,
            ])->all();
        }

        return collect($paths)->values()->map(function (mixed $path, int $index) use ($labels): array {
            return [
                'label' => $labels[$index] ?? 'Photo '.($index + 1),
                'url' => $this->assetUrl(is_string($path) ? $path : null),
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function documents(DeliveryOrder $deliveryOrder, ?string $documentPath): array
    {
        if (! filled($documentPath)) {
            return [];
        }

        $filename = basename($documentPath);
        $url = $this->assetUrl($documentPath);

        return [[
            'name' => $filename !== '' ? $filename : $deliveryOrder->number.'-signed.pdf',
            'size' => '—',
            'status' => 'Available',
            'url' => $url,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timestamps(DeliveryOrder $deliveryOrder, $pod, $failed): array
    {
        $pickupAt = $deliveryOrder->jobSheet?->checked_in_at;
        $handoverAt = $deliveryOrder->breakBulks
            ->sortByDesc('collected_at')
            ->first()
            ?->collected_at;
        $deliveryAt = $pod?->delivered_at ?? $deliveryOrder->delivered_at;
        $failureAt = $failed?->failed_at ?? $deliveryOrder->failed_at;
        $syncAt = $pod?->synced_at ?? $failed?->synced_at;

        return [
            $this->timestampRow('Pickup', $pickupAt),
            $this->timestampRow('Handover', $handoverAt),
            $this->timestampRow('Delivery', $deliveryAt, highlight: true),
            $this->timestampRow('Failure', $failureAt),
            $this->timestampRow('Synchronization', $syncAt, source: 'System'),
        ];
    }

    /** @return array<string, mixed> */
    private function timestampRow(string $event, mixed $at, bool $highlight = false, string $source = 'Driver App'): array
    {
        $carbon = filled($at) ? Carbon::parse((string) $at) : null;

        return [
            'event' => $event,
            'date' => $carbon?->format('d/m/Y') ?? '—',
            'time' => $carbon?->format('H:i') ?? '—',
            'source' => $carbon ? $source : '—',
            'highlight' => $highlight && filled($carbon),
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
