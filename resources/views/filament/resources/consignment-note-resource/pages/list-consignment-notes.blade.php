@php
    $stripDays = $this->stripDays();
    $hasCsnDate = filled($csnFrom) || filled($csnTo);
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    {{-- CSN list only: filter card fields on phones, the column toggle in the table header row and the CSN date strip. The ow-* classes come from order-workspace-theme. --}}
    <style>
        .csn-list { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); }
        @media (max-width: 639px) {
            .csn-list .ow-filter-top > .ow-field { flex: 1 1 100%; max-width: none; }
            .csn-list .ow-filter-top .ow-actions { margin-left: 0; }
        }

        /*
         * Filament's toolbar above the table only holds the column toggle here (no table search, filters or bulk
         * actions), so it leaves the flow and sits over the right end of the table's header row (3rem high).
         * It is outside the sideways scroller, so the button stays at the right edge while the table scrolls.
         * --csn-head-bg matches the header row: gray-50, or 5% white over the dark table in dark mode.
         */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn { position: relative; --csn-head-bg: rgb(var(--gray-50, 248, 250, 252)); }
        .dark .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn { --csn-head-bg: rgb(29 36 50); }
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn > .fi-ta-header-ctn { position: absolute; top: 1px; right: 1px; z-index: 4; height: 3rem; margin: 0; border: 0; border-radius: 0 calc(.75rem - 1px) 0 0; background: var(--csn-head-bg); box-shadow: none; overflow: visible; }
        /* header labels scrolled under the button fade out instead of being cut off */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn > .fi-ta-header-ctn::before { content: ''; position: absolute; top: 0; bottom: 0; right: 100%; width: 1rem; background: linear-gradient(to right, transparent, var(--csn-head-bg)); pointer-events: none; }
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn > .fi-ta-header-ctn > .fi-ta-header-toolbar { height: 100%; min-height: 0; padding: 0 1.25rem 0 .75rem !important; }
        /* no rows: no header row either, the button just sits in the top corner of the empty state */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn:not(:has(thead)) > .fi-ta-header-ctn { background: transparent; }
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn:not(:has(thead)) > .fi-ta-header-ctn::before { content: none; }
        /* room for the button in the last header cell (the empty actions header) */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-table > thead > tr > th:last-child { padding-inline-end: 3.75rem; }
        /* the CSN theme clips the table card; scroll sideways instead (the CSN no. column stays fixed, see datatable-theme) */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn > .fi-ta-content { overflow-x: auto; overflow-y: hidden; }

        .csn-strip-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .25rem 1rem; margin-bottom: .45rem; }
        .csn-strip-title { font-size: .72rem; font-weight: 600; color: var(--ow-muted); }
        .csn-strip-range { font-weight: 400; color: var(--ow-faint); }
        .csn-strip-links { display: flex; gap: 1rem; }
        .csn-strip { display: flex; align-items: stretch; gap: .5rem; }
        .csn-strip-nav { flex: none; width: 2.25rem; display: grid; place-items: center; border: 1px solid var(--ow-line); border-radius: .6rem; background: var(--ow-bg); color: var(--ow-muted); cursor: pointer; box-shadow: var(--ow-shadow); transition: background-color .12s, color .12s; }
        .csn-strip-nav:hover { background: var(--ow-soft-2); color: var(--ow-text); }
        .csn-strip-nav:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; }
        .csn-strip-nav svg { width: 1.1rem; height: 1.1rem; }
        /* 7 day cards; on narrow screens they keep their width and scroll sideways inside the strip */
        .csn-strip-days { flex: 1 1 auto; min-width: 0; display: grid; grid-template-columns: repeat(7, minmax(6.25rem, 1fr)); gap: .5rem; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: thin; }
        .csn-strip-days[aria-busy='true'] { opacity: .6; }
        .csn-day { scroll-snap-align: start; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; padding: .55rem .5rem; border: 1px solid var(--ow-line); border-radius: .6rem; background: var(--ow-bg); color: var(--ow-text); text-align: center; cursor: pointer; transition: background-color .12s, border-color .12s, color .12s; }
        .csn-day:hover { border-color: var(--ow-faint); }
        .csn-day:focus-visible { outline: 2px solid #3b82f6; outline-offset: -2px; }
        .csn-day-date { display: inline-flex; align-items: center; gap: .3rem; font-size: .8125rem; font-weight: 600; white-space: nowrap; }
        .csn-day-dot { width: .4rem; height: .4rem; border-radius: 9999px; background: var(--ow-link); }
        .csn-day-count { font-size: .72rem; color: var(--ow-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .csn-day.is-empty .csn-day-count { color: var(--ow-faint); }
        /* selected day (or every day inside a CSN date range): tinted, primary border and text */
        .csn-day.is-selected { background: rgba(var(--primary-500, 15, 23, 42), .07); border-color: rgb(var(--primary-500, 15, 23, 42)); color: rgb(var(--primary-500, 15, 23, 42)); box-shadow: inset 0 -3px 0 rgb(var(--primary-500, 15, 23, 42)); }
        .csn-day.is-selected .csn-day-count { color: inherit; opacity: .8; }
        .dark .csn-day.is-selected { background: rgba(var(--primary-300, 159, 162, 170), .14); border-color: rgb(var(--primary-200, 195, 197, 202)); color: rgb(var(--primary-50, 243, 243, 244)); box-shadow: inset 0 -3px 0 rgb(var(--primary-200, 195, 197, 202)); }
        .csn-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    </style>

    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        <div class="ow-page csn-list">
            {{-- Filters: same layout as the Orders page --}}
            <div class="ow-card">
                <div class="ow-filters">
                    <div class="ow-filter-top">
                        <div class="ow-field ow-search">
                            <label for="csn-search">Search</label>
                            <input type="search"
                                   id="csn-search"
                                   wire:model.live.debounce.400ms="search"
                                   class="ow-input"
                                   placeholder="Search CSN, customer, DO, order or invoice no.…">
                        </div>
                        {{-- The date ranges are used most, so they are always shown (not behind "Filters +") --}}
                        <div class="ow-field">
                            <label>CSN date</label>
                            <x-og.date-range from="csnFrom" to="csnTo" :from-value="$csnFrom" :to-value="$csnTo" label="CSN date" />
                        </div>
                        <div class="ow-field">
                            <label>Job date</label>
                            <x-og.date-range from="jobFrom" to="jobTo" :from-value="$jobFrom" :to-value="$jobTo" label="Job date" />
                        </div>
                        <div class="ow-field">
                            <label>Created date</label>
                            <x-og.date-range from="createdFrom" to="createdTo" :from-value="$createdFrom" :to-value="$createdTo" label="Created date" />
                        </div>
                        <div class="ow-actions">
                            <button type="button" wire:click="toggleFilters" @class(['ow-btn', 'ow-pill-dark' => $filtersOpen])>
                                Filters {{ $filtersOpen ? '−' : '+' }}
                            </button>
                            <button type="button" wire:click="resetFilters" class="ow-btn-link">Reset</button>
                        </div>
                    </div>

                    <div class="ow-fgrid ow-fgrid-5">
                        <div class="ow-field">
                            <label>Customer</label>
                            <select wire:model.live="customer" class="ow-select">
                                @foreach ($this->customerOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Order type</label>
                            <select wire:model.live="orderType" class="ow-select">
                                @foreach ($this->orderTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Service</label>
                            <select wire:model.live="serviceType" class="ow-select">
                                @foreach ($this->serviceTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Payment status</label>
                            <select wire:model.live="paymentStatus" class="ow-select">
                                @foreach ($this->paymentStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Transfer code</label>
                            <select wire:model.live="transferCode" class="ow-select">
                                @foreach ($this->transferCodeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if ($filtersOpen)
                        <div class="ow-fgrid">
                            <div class="ow-field">
                                <label>Billing type</label>
                                <select wire:model.live="billingType" class="ow-select">
                                    @foreach ($this->billingTypeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>SA location prefix</label>
                                <select wire:model.live="saLocation" class="ow-select">
                                    @foreach ($this->saLocationOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Salesperson</label>
                                <select wire:model.live="salesperson" class="ow-select">
                                    @foreach ($this->salespersonOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="ow-fgrid">
                            <div class="ow-field">
                                <label>Driver</label>
                                <select wire:model.live="driver" class="ow-select">
                                    @foreach ($this->driverOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Main lorry</label>
                                <select wire:model.live="lorry" class="ow-select">
                                    @foreach ($this->lorryOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Order</label>
                                <select wire:model.live="quotation" class="ow-select">
                                    @foreach ($this->quotationOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Claimed</label>
                                <select wire:model.live="claimed" class="ow-select">
                                    @foreach ($this->yesNoOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="ow-fgrid">
                            <div class="ow-field">
                                <label>Assigned to lorry</label>
                                <select wire:model.live="assigned" class="ow-select">
                                    @foreach ($this->yesNoOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Has subsheets</label>
                                <select wire:model.live="hasSubsheets" class="ow-select">
                                    @foreach ($this->yesNoOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ow-field">
                                <label>Amount · Min (MYR)</label>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="amountMin" class="ow-input">
                            </div>
                            <div class="ow-field">
                                <label>Amount · Max (MYR)</label>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="amountMax" class="ow-input">
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- CSN date strip: one card per day with its CSN count (other filters and the tab apply); click a day to show only that day --}}
            <section aria-label="CSN date">
                <div class="csn-strip-head">
                    <span class="csn-strip-title">CSN date <span class="csn-strip-range">· {{ $this->stripRangeLabel() }}</span></span>
                    <span class="csn-strip-links">
                        @unless ($this->stripShowsToday())
                            <button type="button" wire:click="stripToday" class="ow-btn-link">Today</button>
                        @endunless
                        @if ($hasCsnDate)
                            <button type="button" wire:click="clearCsnDate" class="ow-btn-link">Clear CSN date</button>
                        @endif
                    </span>
                </div>

                <div class="csn-strip">
                    <button type="button" class="csn-strip-nav" wire:click="shiftStrip(-7)" aria-label="Previous 7 days">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12.5 15l-5-5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>

                    <div class="csn-strip-days" role="group" aria-label="Days" wire:loading.attr="aria-busy" wire:target="selectStripDay, shiftStrip, stripToday">
                        @foreach ($stripDays as $day)
                            <button type="button"
                                    wire:key="csn-day-{{ $day['date'] }}"
                                    wire:click="selectStripDay('{{ $day['date'] }}')"
                                    aria-pressed="{{ $day['selected'] ? 'true' : 'false' }}"
                                    @if ($day['today']) title="Today" @endif
                                    @class([
                                        'csn-day',
                                        'is-selected' => $day['selected'],
                                        'is-today' => $day['today'],
                                        'is-empty' => $day['count'] === 0,
                                    ])>
                                <span class="csn-day-date">
                                    @if ($day['today'])
                                        <span class="csn-day-dot" aria-hidden="true"></span><span class="csn-sr">Today,</span>
                                    @endif
                                    {{ $day['label'] }}
                                </span>
                                {{-- written out: Str::plural('CSN') follows the word's case and gives "CSNS" --}}
                                <span class="csn-day-count">{{ $day['count'] === 0 ? 'No CSN' : $day['count'].' CSN'.($day['count'] === 1 ? '' : 's') }}</span>
                            </button>
                        @endforeach
                    </div>

                    <button type="button" class="csn-strip-nav" wire:click="shiftStrip(7)" aria-label="Next 7 days">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
            </section>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
