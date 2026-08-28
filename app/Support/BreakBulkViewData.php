<?php

namespace App\Support;

use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\BreakBulkResource;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class BreakBulkViewData
{
    /** @return array<string, mixed> */
    public function for(BreakBulk $breakBulk): array
    {
        $breakBulk->loadMissing([
            'deliveryOrder.consignmentNote.customer',
            'deliveryOrder.jobSheet',
            'deliveryOrder.driver',
            'deliveryOrder.lorry',
            'consignmentNote',
            'jobSheet',
            'originalDriver',
            'originalLorry',
            'replacementDriver',
            'replacementLorry',
            'requestedByDriver',
            'creator',
        ]);

        $do = $breakBulk->deliveryOrder;
        $csn = $breakBulk->consignmentNote ?? $do?->consignmentNote;

        return [
            'break_bulk' => $breakBulk,
            'header' => [
                'title' => 'Breakbulk Reassignment',
                'csn_number' => $csn?->number,
                'do_number' => $do?->number,
                'job_sheet_number' => $breakBulk->jobSheet?->number ?? $do?->jobSheet?->number,
                'status_label' => $this->displayStatus($breakBulk),
                'status_color' => $this->displayStatusColor($breakBulk),
            ],
            'reference' => [
                'do_number' => $do?->number,
                'job_sheet_number' => $breakBulk->jobSheet?->number ?? $do?->jobSheet?->number,
                'date' => $breakBulk->created_at?->format('d/m/Y'),
                'status_label' => 'BREAK-BULK',
                'customer_name' => $csn?->customer_name ?? $csn?->customer?->name,
                'location' => $breakBulk->location,
                'destination' => $csn?->delivery_address,
                'reason' => $breakBulk->reason,
                'time_reported' => $breakBulk->created_at?->format('d/m/Y H:i'),
                'remarks' => $breakBulk->revoke_reason ?? 'Recorded',
                'proof_available' => filled($breakBulk->photo_paths),
            ],
            'photos' => $this->photos($breakBulk),
            'photos_meta' => [
                'uploaded_by' => $breakBulk->requestedByDriver?->name
                    ?? $breakBulk->originalDriver?->name
                    ?? 'Driver',
                'related_do' => $do?->number,
            ],
            'reassignment' => [
                'editable' => $breakBulk->status === 'active',
                'original_driver' => $breakBulk->originalDriver?->name ?? $do?->driver?->name,
                'original_lorry' => $breakBulk->originalLorry?->registration_no ?? $do?->lorry?->registration_no,
                'break_bulk_do' => $do?->number,
                'replacement_driver_id' => $breakBulk->replacement_driver_id,
                'replacement_lorry_id' => $breakBulk->replacement_lorry_id,
                'replacement_driver' => $breakBulk->replacementDriver?->name,
                'replacement_lorry' => $breakBulk->replacementLorry?->registration_no,
            ],
            'driver_options' => $this->driverOptions(),
            'lorry_options' => $this->lorryOptions(),
            'audit_rows' => $this->auditRows($breakBulk),
            'list_url' => Filament::getTenant()
                ? BreakBulkResource::getUrl('index', [], true, null, Filament::getTenant())
                : BreakBulkResource::getUrl('index'),
        ];
    }

    public static function displayStatus(BreakBulk $breakBulk): string
    {
        return match ($breakBulk->status) {
            'completed' => 'Completed',
            'revoked' => 'Revoked',
            default => 'Pending',
        };
    }

    public static function displayStatusColor(BreakBulk $breakBulk): string
    {
        return match ($breakBulk->status) {
            'completed' => 'success',
            'revoked' => 'danger',
            default => 'info',
        };
    }

    public static function sourceLabel(BreakBulk $breakBulk): string
    {
        return filled($breakBulk->requested_by_driver_id) ? 'Driver Request' : 'Manual Admin';
    }

    /** @return array<int, array<string, mixed>> */
    private function photos(BreakBulk $breakBulk): array
    {
        $paths = $breakBulk->photo_paths ?? [];
        $labels = ['Delivery Location', 'Premise Entrance', 'Supporting Situation'];

        if (! is_array($paths) || $paths === []) {
            return collect($labels)->map(fn (string $label): array => [
                'label' => $label,
                'url' => null,
            ])->all();
        }

        return collect($paths)->values()->map(function (mixed $path, int $index) use ($labels): array {
            $url = is_string($path) && filled($path)
                ? (str_starts_with($path, 'http') ? $path : asset('storage/'.$path))
                : null;

            return [
                'label' => $labels[$index] ?? 'Photo '.($index + 1),
                'url' => $url,
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
    private function auditRows(BreakBulk $breakBulk): array
    {
        $activities = Activity::query()
            ->where('subject_type', BreakBulk::class)
            ->where('subject_id', $breakBulk->id)
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get();

        $drivers = Driver::query()->pluck('name', 'id');
        $lorries = Lorry::query()->pluck('registration_no', 'id');

        return $activities
            ->flatMap(fn (Activity $activity): array => $this->rowsFromActivity($activity, $drivers, $lorries))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int|string, string|null>  $drivers
     * @param  Collection<int|string, string|null>  $lorries
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromActivity(Activity $activity, Collection $drivers, Collection $lorries): array
    {
        $user = $activity->causer?->name ?? 'System';
        $timestamp = $activity->created_at;
        $dateTime = $timestamp?->format('d/m/Y H:i') ?? '—';

        if ($activity->description === 'created') {
            return [[
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => '—',
                'replacement_driver' => '—',
                'reason' => 'Break-Bulk Created',
                'user' => $user,
            ]];
        }

        if ($activity->description !== 'updated') {
            return [[
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => '—',
                'replacement_driver' => '—',
                'reason' => ucfirst(str_replace('_', ' ', (string) $activity->description)),
                'user' => $user,
            ]];
        }

        $old = $activity->properties?->get('old', []) ?? [];
        $new = $activity->properties?->get('attributes', []) ?? [];
        $rows = [];

        if (array_key_exists('replacement_driver_id', $new) && ($old['replacement_driver_id'] ?? null) != ($new['replacement_driver_id'] ?? null)) {
            $rows[] = [
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => (string) ($drivers[$old['replacement_driver_id'] ?? $old['original_driver_id'] ?? ''] ?? $drivers[$old['original_driver_id'] ?? ''] ?? '—'),
                'replacement_driver' => (string) ($drivers[$new['replacement_driver_id'] ?? ''] ?? '—'),
                'reason' => 'Driver Reassignment',
                'user' => $user,
            ];
        }

        if (array_key_exists('replacement_lorry_id', $new) && ($old['replacement_lorry_id'] ?? null) != ($new['replacement_lorry_id'] ?? null)) {
            $rows[] = [
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => (string) ($lorries[$old['replacement_lorry_id'] ?? $old['original_lorry_id'] ?? ''] ?? $lorries[$old['original_lorry_id'] ?? ''] ?? '—'),
                'replacement_driver' => (string) ($lorries[$new['replacement_lorry_id'] ?? ''] ?? '—'),
                'reason' => 'Lorry Reassignment',
                'user' => $user,
            ];
        }

        foreach (['handover_status', 'status'] as $field) {
            if (! array_key_exists($field, $new) || ($old[$field] ?? null) == ($new[$field] ?? null)) {
                continue;
            }

            $rows[] = [
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => '—',
                'replacement_driver' => '—',
                'reason' => ucfirst(str_replace('_', ' ', $field)).': '.($old[$field] ?? '—').' → '.($new[$field] ?? '—'),
                'user' => $user,
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'sort_at' => $timestamp?->timestamp ?? 0,
                'date_time' => $dateTime,
                'reassignment_type' => 'BREAKBULK',
                'original_driver' => '—',
                'replacement_driver' => '—',
                'reason' => 'Updated',
                'user' => $user,
            ];
        }

        return $rows;
    }
}
