<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;

/**
 * Section P: what a customer (portal) or consignee (public tracking link) sees of a consignment: its progress
 * from approval to completion, and each delivery order with its proof of delivery or failure details.
 */
class PortalDeliveryView
{
    public const RELATIONS = [
        'quotation:id,number,confirmed_at,created_at',
        'deliveryOrders.lorry', 'deliveryOrders.driver', 'deliveryOrders.subsheet',
        'deliveryOrders.proofOfDelivery', 'deliveryOrders.failedDelivery',
    ];

    /** @return list<array{label: string, done: bool, at: ?string}> */
    public function timeline(ConsignmentNote $csn): array
    {
        $dos = $csn->deliveryOrders;
        $main = $dos->first(fn (DeliveryOrder $do) => ! $do->parent_do_id && ! $do->subsheet_id);
        $status = $csn->status instanceof CsnStatus ? $csn->status : CsnStatus::tryFrom((string) $csn->status);
        $delivered = $status === CsnStatus::Delivered || $main?->status === DeliveryOrderStatus::Delivered;
        $moving = $delivered || $status === CsnStatus::InTransit
            || $dos->contains(fn (DeliveryOrder $do) => in_array($do->status, [DeliveryOrderStatus::InTransit, DeliveryOrderStatus::Delivered, DeliveryOrderStatus::Failed], true));
        $assigned = $moving || $status === CsnStatus::Assigned || $main?->lorry_id;

        return [
            ['label' => 'Order approved', 'done' => true, 'at' => $this->fmt($csn->quotation?->confirmed_at ?? $csn->quotation?->created_at)],
            ['label' => 'Consignment note issued', 'done' => true, 'at' => $this->fmt($csn->issued_at ?? $csn->created_at)],
            ['label' => 'Assigned to lorry', 'done' => (bool) $assigned, 'at' => $assigned ? $this->fmt($main?->created_at) : null],
            ['label' => 'In transit', 'done' => $moving, 'at' => null],
            ['label' => 'Delivered', 'done' => $delivered, 'at' => $delivered ? $this->fmt($main?->proofOfDelivery?->delivered_at ?? $main?->delivered_at) : null],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function deliveryOrders(ConsignmentNote $csn, bool $withTrackingLinks = true): array
    {
        return $csn->deliveryOrders
            ->sortBy(fn (DeliveryOrder $do) => [$do->subsheet_id || $do->parent_do_id ? 1 : 0, $do->id])
            ->values()
            ->map(fn (DeliveryOrder $do) => $this->deliveryOrder($do, $withTrackingLinks))
            ->all();
    }

    /** @return array<string, mixed> */
    public function deliveryOrder(DeliveryOrder $do, bool $withTrackingLink = true): array
    {
        $status = $do->status instanceof DeliveryOrderStatus ? $do->status : DeliveryOrderStatus::tryFrom((string) $do->status);
        $pod = $do->proofOfDelivery;
        $failed = $do->failedDelivery;
        $trackingUrl = $withTrackingLink && $do->tracking_token ? route('tracking.show', $do->tracking_token) : null;

        return [
            'id' => $do->id,
            'number' => $do->number,
            'kind' => match (true) {
                (bool) $do->subsheet_id => (Subsheet::TYPES[$do->subsheet?->task_type] ?? 'Subsheet').' leg',
                (bool) $do->is_duplicate => 'Re-delivery',
                (bool) $do->parent_do_id => 'Additional leg',
                default => 'Main delivery',
            },
            'status' => $status?->getLabel() ?? ucfirst((string) $do->status),
            'tone' => match ($status) {
                DeliveryOrderStatus::Delivered => 'green',
                DeliveryOrderStatus::Failed, DeliveryOrderStatus::Cancelled => 'red',
                DeliveryOrderStatus::InTransit => 'amber',
                default => 'blue',
            },
            'lorry' => $do->lorry?->registration_no,
            'driver' => $do->driver?->name,
            'pod' => $pod ? [
                'recipient' => $pod->recipient_name,
                'delivered_at' => $this->fmt($pod->delivered_at),
                'remarks' => $pod->remarks,
                'photos' => collect($pod->photo_paths ?? [])->map(fn ($p) => DriverTaskPayload::url($p))->filter()->values()->all(),
                'signature' => DriverTaskPayload::url($pod->signature_path),
                'document' => DriverTaskPayload::url($pod->pod_document_path),
            ] : null,
            'failure' => $failed ? [
                'reason' => FailedDelivery::REASONS[$failed->reason] ?? $failed->reason,
                'remarks' => $failed->remarks,
                'failed_at' => $this->fmt($failed->failed_at ?? $do->failed_at),
                'photos' => collect($failed->photo_paths ?? [])->map(fn ($p) => DriverTaskPayload::url($p))->filter()->values()->all(),
            ] : null,
            'tracking_url' => $trackingUrl,
            'qr' => $trackingUrl ? QrCodeImage::dataUri($trackingUrl, 4) : null,
        ];
    }

    private function fmt($date): ?string
    {
        return $date ? $date->timezone(config('app.timezone'))->format('d M Y, H:i') : null;
    }
}
