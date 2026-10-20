{{-- A lorry's maintenance records, by type (insurance, road tax, service, …) --}}
@php
    $lorry = $getRecord();
    $types = \App\Filament\Resources\VehicleMaintenanceResource::TYPES;
    $groups = $lorry->maintenanceRecords->sortByDesc(fn ($r) => ($r->expiry_date ?? $r->next_service_date ?? $r->service_date)?->timestamp ?? 0)->groupBy('type');
    $due = now()->addDays((int) config('og.vehicle.expiry_alert_days', 30));
@endphp
<style>
    .vmr-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
    .vmr-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .vmr-table th, .vmr-table td { padding: .5rem .8rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; vertical-align: top; }
    .vmr-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; white-space: nowrap; }
    .vmr-table .vmr-num { text-align: right; white-space: nowrap; }
    .vmr-due { color: rgb(220 38 38); font-weight: 600; }
    .vmr-sub { font-size: .75rem; color: rgb(100 116 139); }
    .dark .vmr-wrap, .dark .vmr-table th, .dark .vmr-table td { border-color: rgb(55 65 81); }
    .dark .vmr-table th { background: rgb(17 24 39); color: rgb(148 163 184); }
    .dark .vmr-due { color: rgb(248 113 113); }
</style>
@if ($groups->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No maintenance record yet. Use Edit to add insurance, road tax, service and more.</p>
@else
    <div class="vmr-wrap">
        <table class="vmr-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Service / start</th>
                    <th>Expiry</th>
                    <th>Mileage</th>
                    <th>Next service</th>
                    <th class="vmr-num">Cost</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type => $label)
                    @foreach ($groups->get($type, collect()) as $record)
                        <tr>
                            <td><strong>{{ $label }}</strong></td>
                            <td>{{ $record->service_date?->format('d M Y') ?? '—' }}</td>
                            <td @class(['vmr-due' => $record->expiry_date && $record->expiry_date->lte($due) && $record->status === 'active'])>{{ $record->expiry_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $record->mileage ? number_format((float) $record->mileage).' km' : '—' }}</td>
                            <td @class(['vmr-due' => $record->next_service_date && $record->next_service_date->lte($due) && $record->status === 'active'])>
                                {{ $record->next_service_date?->format('d M Y') ?? '—' }}
                                @if ($record->next_service_mileage)<div class="vmr-sub">at {{ number_format((float) $record->next_service_mileage) }} km</div>@endif
                            </td>
                            <td class="vmr-num">{{ $record->cost !== null ? 'RM '.number_format((float) $record->cost, 2) : '—' }}</td>
                            <td>{{ ucfirst((string) $record->status) }}</td>
                            <td>
                                {{ $record->notes ?: '' }}
                                @if ($record->attachment_path)<div><a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($record->attachment_path) }}" target="_blank" class="text-primary-600">Attachment</a></div>@endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif
