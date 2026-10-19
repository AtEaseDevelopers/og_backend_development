@php
    $stripDays = $this->stripDays();
    $hasDate = filled($dateFrom) || filled($dateTo);
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    {{-- the filter card and day strip of the CSN list (ow-* classes: order-workspace-theme) --}}
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

            {{-- quotation date strip: one card per day with its quotation count; click a day to show only that day --}}
            <section aria-label="Quotation date">
                <div class="ow-strip-head">
                    <span class="ow-strip-title">Quotation date <span class="ow-strip-range">· {{ $this->stripRangeLabel() }}</span></span>
                    <span class="ow-strip-links">
                        @unless ($this->stripShowsToday())
                            <button type="button" wire:click="stripToday" class="ow-btn-link">Today</button>
                        @endunless
                        @if ($hasDate)
                            <button type="button" wire:click="clearDate" class="ow-btn-link">Clear quotation date</button>
                        @endif
                    </span>
                </div>

                <div class="ow-strip">
                    <button type="button" class="ow-strip-nav" wire:click="shiftStrip(-7)" aria-label="Previous 7 days">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12.5 15l-5-5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>

                    <div class="ow-strip-days" role="group" aria-label="Days" wire:loading.attr="aria-busy" wire:target="selectStripDay, shiftStrip, stripToday">
                        @foreach ($stripDays as $day)
                            <button type="button"
                                    wire:key="quote-day-{{ $day['date'] }}"
                                    wire:click="selectStripDay('{{ $day['date'] }}')"
                                    aria-pressed="{{ $day['selected'] ? 'true' : 'false' }}"
                                    @if ($day['today']) title="Today" @endif
                                    @class([
                                        'ow-day',
                                        'is-selected' => $day['selected'],
                                        'is-today' => $day['today'],
                                        'is-empty' => $day['count'] === 0,
                                    ])>
                                <span class="ow-day-date">
                                    @if ($day['today'])
                                        <span class="ow-day-dot" aria-hidden="true"></span><span class="ow-sr">Today,</span>
                                    @endif
                                    {{ $day['label'] }}
                                </span>
                                <span class="ow-day-count">{{ $day['count'] === 0 ? 'No quotation' : $day['count'].' quotation'.($day['count'] === 1 ? '' : 's') }}</span>
                            </button>
                        @endforeach
                    </div>

                    <button type="button" class="ow-strip-nav" wire:click="shiftStrip(7)" aria-label="Next 7 days">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
            </section>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
