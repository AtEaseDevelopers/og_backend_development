<x-filament-panels::page>
    <style>
        .cml-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; overflow: hidden; overflow-x: auto; }
        .cml-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
        .cml-table th, .cml-table td { padding: .55rem .9rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; vertical-align: top; }
        .cml-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(100 116 139); background: rgb(248 250 252); font-weight: 600; white-space: nowrap; }
        .cml-table .cml-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .cml-sub { font-size: .75rem; color: rgb(100 116 139); }
        .cml-changed { color: rgb(180 83 9); }
        .cml-input { width: 7.5rem; height: 2.2rem; padding: 0 .6rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; text-align: right; background: #fff; color: inherit; }
        .cml-card { border: 1px solid rgb(229 231 235); border-radius: .9rem; padding: 1rem 1.1rem; background: #fff; }
        .cml-card + .cml-card { margin-top: 1rem; }
        .cml-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
        .cml-head a { font-weight: 600; color: rgb(37 99 235); }
        .cml-summary { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; margin-bottom: 1rem; font-size: .9rem; }
        .cml-actions { display: flex; justify-content: flex-end; margin-top: 1rem; }
        .dark .cml-wrap, .dark .cml-table th, .dark .cml-table td, .dark .cml-card { border-color: rgb(55 65 81); }
        .dark .cml-table th { background: rgb(17 24 39); color: rgb(148 163 184); }
        .dark .cml-sub { color: rgb(148 163 184); }
        .dark .cml-input { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .cml-card { background: rgb(17 24 39 / .4); }
        .dark .cml-head a { color: rgb(96 165 250); }
    </style>
    @php
        $lorry = $this->lorry();
        $lines = $this->lines();
        $editable = $this->editable();
        $drivers = $lines->groupBy(fn ($line) => $line->driver?->name ?? 'Driver removed')->map(fn ($l) => $l->sum(fn ($line) => $this->enteredAmount($line)));
    @endphp
    <div class="cml-summary">
        <span>Batch <strong>{{ $this->getRecord()->number }}</strong> · {{ $this->getRecord()->month }}</span>
        <span>Branch <strong>{{ $lorry?->branch?->name ?? '—' }}</strong></span>
        <span>Commission <strong>RM {{ number_format($lines->sum(fn ($line) => $this->enteredAmount($line)), 2) }}</strong></span>
        <span>Drivers: @foreach ($drivers as $name => $amount)<strong>{{ $name }}</strong> RM {{ number_format($amount, 2) }}@if (! $loop->last) · @endif @endforeach</span>
    </div>

    {{-- date first, then the job sheets of the day (expandable), then the CSNs of a job sheet (user, 3 Nov 2026) --}}
    @php
        $byDate = $lines->groupBy(fn ($line) => $line->deliveryOrder?->jobSheet?->operating_date?->format('Y-m-d') ?? '')->sortKeysDesc();
    @endphp
    @forelse ($byDate as $date => $dateLines)
        @php $daySheets = $dateLines->groupBy(fn ($line) => (int) ($line->deliveryOrder?->job_sheet_id ?? 0)); @endphp
        <div class="cml-card" x-data="{ open: true }">
            <div class="cml-head" style="cursor:pointer" x-on:click="open = ! open" title="Show / hide the job sheets of this day">
                <span>
                    <strong>{{ $date !== '' ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : 'No date' }}</strong>
                    <span class="cml-sub"> · {{ $daySheets->count() }} job sheet(s) · {{ $dateLines->pluck('consignment_note_id')->unique()->count() }} CSN(s)</span>
                </span>
                <span><strong>RM {{ number_format($dateLines->sum(fn ($line) => $this->enteredAmount($line)), 2) }}</strong> <span class="cml-sub" x-text="open ? '▲' : '▼'"></span></span>
            </div>
            <div x-show="open" x-cloak>
                @foreach ($daySheets as $sheetId => $sheetLines)
                    @php $sheet = $sheetLines->first()->deliveryOrder?->jobSheet; @endphp
                    <div class="cml-card" x-data="{ openSheet: false }" style="margin-top:.6rem">
                        <div class="cml-head" style="cursor:pointer;margin-bottom:0" x-on:click="openSheet = ! openSheet" title="Show / hide the CSNs of this job sheet">
                            <span>
                                @if ($sheetId)
                                    <a href="{{ \App\Filament\Resources\CommissionBatchResource::getUrl('job-sheet', ['record' => $this->getRecord(), 'jobSheet' => $sheetId]) }}" x-on:click.stop>Job sheet {{ $sheet?->number }}</a>
                                @else
                                    <strong>No job sheet</strong>
                                @endif
                                <span class="cml-sub"> · {{ $sheetLines->pluck('driver.name')->filter()->unique()->implode(', ') ?: 'Driver removed' }} · {{ $sheetLines->pluck('consignment_note_id')->unique()->count() }} CSN(s)</span>
                            </span>
                            <span><strong>RM {{ number_format($sheetLines->sum(fn ($line) => $this->enteredAmount($line)), 2) }}</strong> <span class="cml-sub" x-text="openSheet ? '▲' : '▼'"></span></span>
                        </div>
                        <div x-show="openSheet" x-cloak style="margin-top:.75rem">
                            @include('filament.resources.commission-batch-resource.partials.commission-lines', ['lines' => $sheetLines, 'editable' => $editable])
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No commission line for this lorry in the batch.</p>
    @endforelse

    @if ($editable && $lines->isNotEmpty())
        <div class="cml-actions">
            <x-filament::button wire:click="save" icon="heroicon-o-check">Save · generate PO</x-filament::button>
        </div>
    @elseif (! $editable)
        <p class="cml-sub">The batch is confirmed: its commission is locked.</p>
    @endif
</x-filament-panels::page>
