@php
    $groups = $this->getDailyJobSheets();
    $total = $groups->flatten()->count();
    $date = $this->data['operating_date'] ?? null;
    $selectedLorry = (int) ($this->data['lorry_id'] ?? 0);
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Cross-branch lorry assignment</x-slot>
        <x-slot name="description">
            Assign a CSN from any source branch to a lorry registered under any company.
            Source branch remains sticky for billing and commission.
        </x-slot>

        <form wire:submit="assign" class="space-y-6">
            {{ $this->form }}
            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Assign to lorry / job sheet
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Daily job sheets
            <span class="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $total }}</span>
        </x-slot>
        <x-slot name="description">
            {{ $date ? \Illuminate\Support\Carbon::parse($date)->format('l, d M Y') : 'Select an operating date' }}
            · Every trip already opened on this date. Use a row to pick its lorry above.
        </x-slot>

        @if ($total === 0)
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10">
                No job sheets yet for this date. Assigning a CSN to a lorry opens its first trip.
            </div>
        @else
            <div class="space-y-6">
                @foreach ($groups as $branchCode => $sheets)
                    <div wire:key="js-branch-{{ $branchCode }}">
                        <div class="mb-2 flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-building-office-2" class="h-4 w-4 text-gray-400" />
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $branchCode }} — {{ $sheets->first()->operatingBranch?->name }}
                            </h3>
                            <span class="text-xs text-gray-500">{{ $sheets->count() }} {{ \Illuminate\Support\Str::plural('trip', $sheets->count()) }}</span>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2.5">Job sheet</th>
                                        <th class="px-4 py-2.5">Trip</th>
                                        <th class="px-4 py-2.5">Lorry</th>
                                        <th class="px-4 py-2.5">Driver</th>
                                        <th class="px-4 py-2.5 text-center">Tasks</th>
                                        <th class="px-4 py-2.5">Status</th>
                                        <th class="px-4 py-2.5 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($sheets as $sheet)
                                        @php
                                            $status = $sheet->status;
                                            $isSelected = $selectedLorry === (int) $sheet->lorry_id;
                                        @endphp
                                        <tr wire:key="js-row-{{ $sheet->id }}" @class(['bg-primary-50/60 dark:bg-primary-500/10' => $isSelected])>
                                            <td class="whitespace-nowrap px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                                                <a href="{{ \App\Filament\Resources\JobSheetResource::getUrl('view', ['record' => $sheet]) }}" class="hover:underline">{{ $sheet->number }}</a>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5">
                                                <x-filament::badge color="gray">{{ $sheet->tripLabel() }}</x-filament::badge>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5">
                                                <span class="font-medium">{{ $sheet->lorry?->registration_no ?? '—' }}</span>
                                                <span class="text-xs text-gray-500">{{ $sheet->lorry?->branch?->code }}</span>
                                                @if ($sheet->is_shared_dispatch)
                                                    <x-filament::badge color="info" size="sm" class="ms-1 inline-flex">Shared</x-filament::badge>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5">
                                                @if ($sheet->driver)
                                                    {{ $sheet->driver->name }}
                                                @else
                                                    <span class="text-xs italic text-gray-400">No driver</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-center tabular-nums">{{ $sheet->delivery_orders_count }}</td>
                                            <td class="whitespace-nowrap px-4 py-2.5">
                                                <x-filament::badge :color="$status?->getColor() ?? 'gray'">{{ $status?->getLabel() ?? '—' }}</x-filament::badge>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                                @if ($sheet->lorry_id && $status?->value !== 'completed')
                                                    @if ($isSelected)
                                                        <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Selected</span>
                                                    @else
                                                        <x-filament::link tag="button" type="button" size="sm" wire:click="useLorry({{ $sheet->lorry_id }})">
                                                            Use this lorry
                                                        </x-filament::link>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
