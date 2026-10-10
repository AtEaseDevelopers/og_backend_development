@php
    $schedule = $this->getSchedule();
    $day = $schedule['date'];
    $stripDays = $this->stripDays();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <span class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">Operating date</span>
                    <x-og.date-range single from="date" :from-value="$date" label="Operating date" style="min-width: 0; width: 12.5rem" />
                </div>
            </div>
            <div>
                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white" for="schedule-branch">Branch</label>
                <select id="schedule-branch" wire:model.live="branch" class="fi-select-input block w-64 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                    @foreach ($this->branchOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ms-auto text-sm text-gray-500">
                <span class="font-semibold text-gray-950 dark:text-white">{{ $day->format('d.m.Y (D)') }}</span>
                · {{ $schedule['total_trips'] }} {{ \Illuminate\Support\Str::plural('trip', $schedule['total_trips']) }}
                · {{ $schedule['total_tasks'] }} {{ \Illuminate\Support\Str::plural('delivery', $schedule['total_tasks']) }}
            </div>
        </div>
    </x-filament::section>

    {{-- date strip (as on Orders / CSN management): 7 days with their trip counts for the branch; click a day to show it --}}
    <section class="ow-page" aria-label="Operating date">
        <div class="ow-strip-head">
            <span class="ow-strip-title">Operating date <span class="ow-strip-range">· {{ $this->stripRangeLabel() }}</span></span>
            <span class="ow-strip-links">
                @unless ($this->stripShowsToday())
                    <button type="button" wire:click="stripToday" class="ow-btn-link">Today</button>
                @endunless
            </span>
        </div>

        <div class="ow-strip">
            <button type="button" class="ow-strip-nav" wire:click="shiftStrip(-7)" aria-label="Previous 7 days">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12.5 15l-5-5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>

            <div class="ow-strip-days" role="group" aria-label="Days" wire:loading.attr="aria-busy" wire:target="selectStripDay, shiftStrip, stripToday">
                @foreach ($stripDays as $stripDay)
                    <button type="button"
                            wire:key="dls-day-{{ $stripDay['date'] }}"
                            wire:click="selectStripDay('{{ $stripDay['date'] }}')"
                            aria-pressed="{{ $stripDay['selected'] ? 'true' : 'false' }}"
                            @if ($stripDay['today']) title="Today" @endif
                            @class([
                                'ow-day',
                                'is-selected' => $stripDay['selected'],
                                'is-today' => $stripDay['today'],
                                'is-empty' => $stripDay['count'] === 0,
                            ])>
                        <span class="ow-day-date">
                            @if ($stripDay['today'])
                                <span class="ow-day-dot" aria-hidden="true"></span><span class="ow-sr">Today,</span>
                            @endif
                            {{ $stripDay['label'] }}
                        </span>
                        <span class="ow-day-count">{{ $stripDay['count'] === 0 ? 'No trip' : $stripDay['count'].' '.\Illuminate\Support\Str::plural('trip', $stripDay['count']) }}</span>
                    </button>
                @endforeach
            </div>

            <button type="button" class="ow-strip-nav" wire:click="shiftStrip(7)" aria-label="Next 7 days">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </div>
    </section>

    @if ($schedule['branches'] === [])
        <x-filament::section>
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-white/10">
                No lorry trips scheduled for {{ $day->format('d.m.Y') }}@if ($branch !== 'all') in this branch @endif.
            </div>
        </x-filament::section>
    @endif

    @foreach ($schedule['branches'] as $group)
        <x-filament::section wire:key="dls-{{ $group['code'] }}">
            <x-slot name="heading">
                <span class="font-bold">O&amp;G {{ $group['code'] }}</span>
                <span class="ms-2 font-normal text-gray-500">{{ $group['name'] }}</span>
                <span class="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ count($group['trips']) }}</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table data-og-xtable class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Job sheet</th>
                            <th class="px-3 py-2">Lorry</th>
                            <th class="px-3 py-2">Driver</th>
                            <th class="px-3 py-2">From</th>
                            <th class="px-3 py-2">To</th>
                            <th class="px-3 py-2">Customer(s)</th>
                            <th class="px-3 py-2 text-center">Drops</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($group['trips'] as $trip)
                            <tr wire:key="dls-trip-{{ $trip['id'] }}">
                                <td class="whitespace-nowrap px-3 py-2 font-medium">
                                    <a href="{{ \App\Filament\Resources\JobSheetResource::getUrl('view', ['record' => $trip['id']]) }}" class="hover:underline">{{ $trip['number'] }}</a>
                                    <span class="ms-1 text-xs text-gray-400">{{ $trip['trip_label'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">{{ $trip['lorry'] }}</span>
                                    @if ($trip['shared'])<span class="ms-1 text-xs text-sky-600">[{{ $trip['lorry_branch'] }}]</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $trip['driver'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $trip['origin'] }}</td>
                                <td class="px-3 py-2 font-medium">{{ $trip['destinations'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ implode(', ', $trip['customers']) ?: '—' }}</td>
                                <td class="px-3 py-2 text-center tabular-nums">{{ $trip['task_count'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2"><x-filament::badge :color="$trip['status_color']">{{ $trip['status_label'] }}</x-filament::badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($group['other_orders'] !== [])
                @foreach (collect($group['other_orders'])->groupBy('source_branch') as $sourceBranch => $orders)
                    <div class="mt-4" wire:key="dls-other-{{ $group['code'] }}-{{ $sourceBranch }}">
                        <h4 class="mb-1 text-sm font-bold text-gray-950 dark:text-white">O&amp;G {{ $sourceBranch }} ORDER <span class="font-normal text-gray-500">carried by {{ $group['code'] }} lorries</span></h4>
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($orders as $order)
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-1.5"><span class="rounded bg-sky-100 px-1.5 py-0.5 font-semibold text-sky-900 dark:bg-sky-500/20 dark:text-sky-200">{{ $order['lorry'] }}</span></td>
                                        <td class="whitespace-nowrap px-3 py-1.5">{{ $order['origin'] }}</td>
                                        <td class="px-3 py-1.5 font-medium">{{ $order['destination'] }} <span class="text-xs text-gray-500">({{ $order['customer'] }})</span></td>
                                        <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ $order['csn'] }} · {{ $order['job_sheet'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
