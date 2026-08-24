<?php

namespace App\Support;

use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\JobSheetStatus;
use App\Filament\Resources\JobSheetResource;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class JobSheetAuditLogData
{
    public function __construct(private JobSheetViewData $viewData) {}

    /** @return array<string, mixed> */
    public function for(JobSheet $jobSheet): array
    {
        $context = $this->viewData->for($jobSheet);
        $context['assignment']['editable'] = false;
        $context['audit_rows'] = $this->auditRows($jobSheet);
        $context['view_url'] = Filament::getTenant()
            ? JobSheetResource::getUrl('view', ['record' => $jobSheet], true, null, Filament::getTenant())
            : JobSheetResource::getUrl('view', ['record' => $jobSheet]);

        return $context;
    }

    /** @return array<int, array<string, mixed>> */
    private function auditRows(JobSheet $jobSheet): array
    {
        $activities = Activity::query()
            ->where('subject_type', JobSheet::class)
            ->where('subject_id', $jobSheet->id)
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
        $date = $activity->created_at?->format('d/m/Y') ?? '—';
        $time = $activity->created_at?->format('H:i') ?? '—';

        if ($activity->description === 'created') {
            $attributes = $activity->properties?->get('attributes', []) ?? [];

            return [[
                'sort_at' => $activity->created_at?->timestamp ?? 0,
                'date' => $date,
                'time' => $time,
                'user' => $user,
                'change_type' => 'Job Sheet Created',
                'original_value' => '—',
                'new_value' => $attributes['number'] ?? 'Created',
            ]];
        }

        if ($activity->description !== 'updated') {
            return [[
                'sort_at' => $activity->created_at?->timestamp ?? 0,
                'date' => $date,
                'time' => $time,
                'user' => $user,
                'change_type' => ucfirst(str_replace('_', ' ', (string) $activity->description)),
                'original_value' => '—',
                'new_value' => '—',
            ]];
        }

        $old = $activity->properties?->get('old', []) ?? [];
        $new = $activity->properties?->get('attributes', []) ?? [];
        $rows = [];

        foreach ($new as $field => $newValue) {
            $oldValue = $old[$field] ?? null;

            if ($this->valuesEqual($field, $oldValue, $newValue)) {
                continue;
            }

            $rows[] = [
                'sort_at' => $activity->created_at?->timestamp ?? 0,
                'date' => $date,
                'time' => $time,
                'user' => $user,
                'change_type' => $this->changeTypeLabel($field),
                'original_value' => $this->formatValue($field, $oldValue, $drivers, $lorries),
                'new_value' => $this->formatValue($field, $newValue, $drivers, $lorries),
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'sort_at' => $activity->created_at?->timestamp ?? 0,
                'date' => $date,
                'time' => $time,
                'user' => $user,
                'change_type' => 'Updated',
                'original_value' => '—',
                'new_value' => '—',
            ];
        }

        return $rows;
    }

    private function valuesEqual(string $field, mixed $oldValue, mixed $newValue): bool
    {
        if ($field === 'operating_date') {
            return (string) $oldValue === (string) $newValue;
        }

        return $oldValue == $newValue;
    }

    private function changeTypeLabel(string $field): string
    {
        return match ($field) {
            'status' => 'Status Changed',
            'driver_id' => 'Driver Change',
            'lorry_id' => 'Lorry Change',
            'checked_in_at' => 'Driver Check-in',
            'operating_date' => 'Operating Date Change',
            'operating_branch_id' => 'Branch Change',
            'is_shared_dispatch' => 'Shared Dispatch Flag',
            default => ucfirst(str_replace('_', ' ', $field)).' Change',
        };
    }

    /**
     * @param  Collection<int|string, string|null>  $drivers
     * @param  Collection<int|string, string|null>  $lorries
     */
    private function formatValue(string $field, mixed $value, Collection $drivers, Collection $lorries): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'status' => JobSheetStatus::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'driver_id' => (string) ($drivers[$value] ?? $value),
            'lorry_id' => (string) ($lorries[$value] ?? $value),
            'is_shared_dispatch' => $value ? 'Yes' : 'No',
            'checked_in_at' => filled($value)
                ? \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y H:i')
                : '—',
            'operating_date' => filled($value)
                ? \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y')
                : '—',
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }
}
