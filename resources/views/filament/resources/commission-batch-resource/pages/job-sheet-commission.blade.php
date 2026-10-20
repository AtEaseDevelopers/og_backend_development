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
        $sheet = $this->jobSheet();
        $lines = $this->lines();
        $editable = $this->editable();
    @endphp
    <div class="cml-summary">
        <span>Batch <strong>{{ $this->getRecord()->number }}</strong></span>
        <span>Date <strong>{{ $sheet?->operating_date?->format('d/m/Y') ?? '—' }}</strong></span>
        <span>Lorry <strong>{{ $sheet?->lorry?->registration_no ?? '—' }}</strong></span>
        <span>Driver <strong>{{ $sheet?->driver?->name ?? 'Driver removed' }}</strong></span>
        <span>CSNs <strong>{{ $lines->pluck('consignment_note_id')->unique()->count() }}</strong></span>
        <span>Commission <strong>RM {{ number_format($lines->sum(fn ($line) => $this->enteredAmount($line)), 2) }}</strong></span>
    </div>

    @if ($lines->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No commission line for this job sheet in the batch.</p>
    @else
        @include('filament.resources.commission-batch-resource.partials.commission-lines', ['lines' => $lines, 'editable' => $editable])
    @endif

    @if ($editable && $lines->isNotEmpty())
        <div class="cml-actions">
            <x-filament::button wire:click="save" icon="heroicon-o-check">Save · generate PO</x-filament::button>
        </div>
    @elseif (! $editable)
        <p class="cml-sub">The batch is confirmed: its commission is locked.</p>
    @endif
</x-filament-panels::page>
