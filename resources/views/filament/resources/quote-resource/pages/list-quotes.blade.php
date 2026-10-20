@php
    $hasDate = filled($dateFrom) || filled($dateTo);
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    {{-- the filter card of the CSN list (ow-* classes: order-workspace-theme) --}}
    <style>
        .quote-list { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); }
        .quote-list .ow-filter-top > .ow-field { flex: 1 1 10rem; min-width: 0; }
        .quote-list .ow-filter-top > .ow-field.ow-search { flex: 1.6 1 15rem; max-width: none; }
        @media (max-width: 639px) {
            .quote-list .ow-filter-top > .ow-field { flex: 1 1 100%; max-width: none; }
            .quote-list .ow-filter-top .ow-actions { margin-left: 0; }
        }
    </style>

    <div class="flex flex-col gap-y-6">
        <div class="ow-page quote-list">
            <div class="ow-card">
                <div class="ow-filters">
                    <div class="ow-filter-top">
                        <div class="ow-field ow-search">
                            <label for="quote-search">Search</label>
                            <input type="search" id="quote-search" wire:model.live.debounce.400ms="search" class="ow-input" placeholder="Search quotation no., customer or lead…">
                        </div>
                        <div class="ow-field">
                            <label>Quotation date</label>
                            <x-og.date-range from="dateFrom" to="dateTo" :from-value="$dateFrom" :to-value="$dateTo" label="Quotation date" />
                        </div>
                        <div class="ow-field">
                            <label>Customer / lead</label>
                            <select wire:model.live="party" class="ow-select">
                                @foreach ($this->partyOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Status</label>
                            <select wire:model.live="status" class="ow-select">
                                @foreach ($this->statusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-actions">
                            <button type="button" wire:click="resetFilters" class="ow-btn-link">Reset</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
