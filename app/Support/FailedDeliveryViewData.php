<?php

namespace App\Support;

use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\FailedDeliveryResource;
use Filament\Facades\Filament;

class FailedDeliveryViewData
{
    /** @return array<string, mixed> */
    public function for(FailedDelivery $failed): array
    {
        $failed->loadMissing([
            'deliveryOrder.consignmentNote.customer',
            'deliveryOrder.sourceBranch',
            'deliveryOrder.jobSheet.operatingBranch',
            'deliveryOrder.lorry',
            'deliveryOrder.driver',
            'driver',
            'replacementDeliveryOrder.driver',
            'replacementDeliveryOrder.lorry',
        ]);

        $do = $failed->deliveryOrder;
        $csn = $do?->consignmentNote;
        $replacementDo = $failed->replacementDeliveryOrder;
        $editable = blank($failed->replacement_do_id);

        return [
            'header' => [
                'do_number' => $do?->number,
                'job_sheet_number' => $do?->jobSheet?->number,
                'status_label' => 'Failed',
                'status_color' => 'danger',
            ],
            'reference' => [
                'do_number' => $do?->number,
                'job_sheet_number' => $do?->jobSheet?->number,
                'date' => $do?->jobSheet?->operating_date?->format('d/m/Y') ?? $failed->failed_at?->format('d/m/Y'),
                'status_label' => 'FAILED',
                'customer_name' => $csn?->customer_name ?? $csn?->customer?->name,
                'destination' => $this->formatDestination($csn?->delivery_city, $csn?->delivery_state, $csn?->delivery_address),
                'branch' => $do?->sourceBranch?->name,
                'reason' => $failed->reason,
                'time_reported' => $failed->failed_at?->format('H:i'),
                'remarks' => $failed->remarks ?? 'Recorded',
                'proof_available' => filled($failed->photo_paths),
            ],
            'photos' => $this->photos($failed->photo_paths),
            'photos_meta' => [
                'uploaded_by' => $failed->driver?->name ?? $do?->driver?->name ?? 'Driver',
                'related_do' => $do?->number,
            ],
            'reassignment_types' => [
                [
                    'value' => 'standard',
                    'label' => 'Standard Reassignment',
                    'description' => 'Move the failed DO to a replacement driver and lorry. The original driver receives no commission.',
                    'commission_title' => 'Original Driver Commission',
                    'commission_value' => 'No Commission',
                    'commission_note' => 'Original driver commission rule is flexible to edit.',
                ],
                [
                    'value' => 'duplicate',
                    'label' => 'Duplicate & Reassign',
                    'description' => 'Keep the failed DO record and create a linked replacement DO. Both drivers may be eligible for commission.',
                    'commission_title' => 'Replacement Driver Commission',
                    'commission_value' => 'Eligible for Commission',
                    'commission_note' => 'Dual commission eligibility applies when duplicate reassignment is used.',
                ],
            ],
            'assignment' => [
                'editable' => $editable,
                'reassignment_option' => $failed->reassignment_option ?? 'standard',
                'original_driver' => $failed->driver?->name ?? $do?->driver?->name,
                'original_lorry' => $do?->lorry?->registration_no,
                'failed_do' => $do?->number,
                'replacement_driver_id' => $replacementDo?->driver_id,
                'replacement_lorry_id' => $replacementDo?->lorry_id,
                'replacement_driver' => $replacementDo?->driver?->name,
                'replacement_lorry' => $replacementDo?->lorry?->registration_no,
                'replacement_do' => $replacementDo?->number,
            ],
            'driver_options' => $this->driverOptions(),
            'lorry_options' => $this->lorryOptions(),
            'audit_rows' => $this->auditRows($failed),
            'list_url' => Filament::getTenant()
                ? FailedDeliveryResource::getUrl('index', [], true, null, Filament::getTenant())
                : FailedDeliveryResource::getUrl('index'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function photos(?array $paths): array
    {
        $labels = [
            'Failure Photo 1 — Delivery Location',
            'Failure Photo 2 — Premise / Entrance',
            'Failure Photo 3 — Supporting Situation',
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

    /** @return array<int|string, string> */
    private function driverOptions(): array
    {
        $query = Driver::query()->where('is_active', true)->orderBy('name');

        if ($companyId = CurrentCompany::id()) {
            $query->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    private function lorryOptions(): array
    {
        $query = Lorry::query()->where('is_active', true)->orderBy('registration_no');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->pluck('registration_no', 'id')->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function auditRows(FailedDelivery $failed): array
    {
        $rows = [[
            'sort_at' => $failed->failed_at?->timestamp ?? 0,
            'date_time' => $failed->failed_at?->format('d/m/Y H:i') ?? '—',
            'reassignment_type' => 'FAILED',
            'original_driver' => $failed->driver?->name ?? '—',
            'replacement_driver' => '—',
            'reason' => $failed->reason ?? 'Failed Delivery Reported',
            'user' => 'Driver',
        ]];

        if (filled($failed->replacement_do_id) && filled($failed->reassignment_option)) {
            $replacementDriver = $failed->replacementDeliveryOrder?->driver?->name ?? '—';

            $rows[] = [
                'sort_at' => $failed->updated_at?->timestamp ?? 0,
                'date_time' => $failed->updated_at?->format('d/m/Y H:i') ?? '—',
                'reassignment_type' => strtoupper(str_replace('_', ' ', (string) $failed->reassignment_option)).' REASSIGNMENT',
                'original_driver' => $failed->driver?->name ?? '—',
                'replacement_driver' => $replacementDriver,
                'reason' => $failed->reason ?? '—',
                'user' => auth()->user()?->name ?? 'Admin',
            ];
        }

        return collect($rows)->sortByDesc('sort_at')->values()->all();
    }

    private function formatDestination(?string $city, ?string $state, ?string $address = null): string
    {
        $parts = array_filter([$city, $state]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return filled($address) ? (string) $address : '—';
    }

    private function assetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
