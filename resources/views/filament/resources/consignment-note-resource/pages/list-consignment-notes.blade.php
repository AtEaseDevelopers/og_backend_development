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
        /* one filter row: the fields share the width (search a bit wider), Filters + / Reset at the end */
        .csn-list .ow-filter-top > .ow-field { flex: 1 1 10rem; min-width: 0; }
        .csn-list .ow-filter-top > .ow-field.ow-search { flex: 1.6 1 15rem; max-width: none; }
        @media (max-width: 639px) {
            .csn-list .ow-filter-top > .ow-field { flex: 1 1 100%; max-width: none; }
            .csn-list .ow-filter-top .ow-actions { margin-left: 0; }
        }

        /* the column toggle sits in the last header cell (shared: public/js/og/excel-filter.js) */
        /* the CSN theme clips the table card; scroll sideways instead (the CSN no. column stays fixed, see datatable-theme) */
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta > .fi-ta-ctn > .fi-ta-content { overflow-x: auto; overflow-y: hidden; }

        /* "CSN returned" and "Payment status" headers on two lines (narrower columns); the room goes to the CSN number column */
        .fi-resource-consignment-notes .fi-table-header-cell-return-status > [role="button"],
        .fi-resource-consignment-notes .fi-table-header-cell-payment-status > [role="button"] { white-space: normal; }
        .fi-resource-consignment-notes .fi-table-header-cell-return-status .fi-ta-header-cell-label,
        .fi-resource-consignment-notes .fi-table-header-cell-payment-status .fi-ta-header-cell-label { display: block; flex: none; max-width: 5.6rem; white-space: normal; line-height: 1.3; }
        .fi-resource-consignment-notes td.fi-table-cell-number { min-width: 19rem; }

        /* lorry plate with a lorry icon (the CSN's lorry, each subsheet's) */
        .ow-lorry-plate { display: inline-flex; align-items: center; gap: .2rem; font-weight: 600; white-space: nowrap; }
        .ow-lorry-icon { width: .95rem; height: .95rem; flex: none; }

        /* Status / Service / Transfer code toggles in one wrapping row, each with its caption above it */
        .csn-toggles { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-end; gap: .75rem 1rem; }
        .csn-toggle-field { display: flex; flex-direction: column; gap: .3rem; min-width: 0; max-width: 100%; }
        .csn-toggle-field nav { margin-left: 0; margin-right: 0; }
        .csn-toggle-caption { padding-left: .35rem; font-size: .75rem; font-weight: 500; color: rgb(100 116 139); }
        .dark .csn-toggle-caption { color: rgb(148 163 184); }

        /* a CSN's subsheets: one line each under its number (tick = select for Assign to lorry) */
        .ow-sub-lines { display: grid; gap: .2rem; margin-top: .35rem; }
        /* orange: a subsheet, not the CSN itself (blue once ticked) */
        .ow-sub-line { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; padding: .2rem .35rem; border: 1px dashed rgb(249 115 22); border-radius: .4rem; background: rgb(255 247 237); font-size: .72rem; color: rgb(124 45 18); cursor: default; }
        .dark .ow-sub-line { border-color: rgb(251 146 60 / .7); background: rgb(124 45 18 / .25); color: rgb(254 215 170); }
        .ow-sub-line .ow-sub-arrow { color: rgb(234 88 12); }
        .ow-sub-line .ow-sub-lorry { color: rgb(154 52 18); }
        .dark .ow-sub-line .ow-sub-arrow, .dark .ow-sub-line .ow-sub-lorry { color: rgb(253 186 116); }
        .ow-sub-line:has(.ow-sub-tick:checked) { border-style: solid; border-color: rgb(59 130 246); background: rgb(239 246 255); }
        .dark .ow-sub-line:has(.ow-sub-tick:checked) { background: rgb(30 58 138 / .35); }
        .ow-sub-tick { width: .9rem; height: .9rem; margin: 0; border-radius: .2rem; cursor: pointer; }
        .ow-sub-tick-gap { display: inline-block; width: .9rem; }
        .ow-sub-arrow { color: rgb(148 163 184); }
        .ow-sub-no { font-weight: 600; font-variant-numeric: tabular-nums; }
        .ow-sub-lorry { color: rgb(100 116 139); }
        .ow-sub-line .ow-csn-tag { margin: 0; }
        .ow-sub-type-transfer { border-color: rgb(191 219 254) !important; color: rgb(29 78 216) !important; background: rgb(239 246 255) !important; }
        .ow-sub-type-break_bulk { border-color: rgb(254 202 202) !important; color: rgb(185 28 28) !important; background: rgb(254 242 242) !important; }

    </style>

    <div class="flex flex-col gap-y-6">
        {{-- Status toggle, then the Service and Transfer code toggles beside it; each named by a caption above it --}}
        <div class="csn-toggles">
            <div class="csn-toggle-field">
                <span class="csn-toggle-caption">Status</span>
                <x-filament-panels::resources.tabs />
            </div>

            <div class="csn-toggle-field">
                <span class="csn-toggle-caption">Service</span>
                <x-filament::tabs label="Service">
                    @foreach ($this->serviceToggles() as $toggle)
                        <x-filament::tabs.item
                            :active="(string) $serviceType === $toggle['value']"
                            :badge="$toggle['count']"
                            wire:click="$set('serviceType', '{{ $toggle['value'] }}')"
                        >
                            {{ $toggle['label'] }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>

            <div class="csn-toggle-field">
                <span class="csn-toggle-caption">Transfer code</span>
                <x-filament::tabs label="Transfer code">
                    @foreach ($this->transferToggles() as $toggle)
                        <x-filament::tabs.item
                            :active="(string) $transferCode === $toggle['value']"
                            :badge="$toggle['count']"
                            wire:click="$set('transferCode', '{{ $toggle['value'] }}')"
                            :title="$toggle['title']"
                        >
                            {{ $toggle['label'] }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>
        </div>

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
                        {{-- one row: search, CSN date, customer, order type, payment status (the rest behind "Filters +") --}}
                        <div class="ow-field">
                            <label>CSN date</label>
                            <x-og.date-range from="csnFrom" to="csnTo" :from-value="$csnFrom" :to-value="$csnTo" label="CSN date" />
                        </div>
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
                            <label>Payment status</label>
                            <select wire:model.live="paymentStatus" class="ow-select">
                                @foreach ($this->paymentStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-actions">
                            <button type="button" wire:click="toggleFilters" @class(['ow-btn', 'ow-pill-dark' => $filtersOpen])>
                                Filters {{ $filtersOpen ? '−' : '+' }}
                            </button>
                            <button type="button" wire:click="resetFilters" class="ow-btn-link">Reset</button>
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
                <div class="ow-strip-head">
                    <span class="ow-strip-title">CSN date <span class="ow-strip-range">· {{ $this->stripRangeLabel() }}</span></span>
                    <span class="ow-strip-links">
                        @unless ($this->stripShowsToday())
                            <button type="button" wire:click="stripToday" class="ow-btn-link">Today</button>
                        @endunless
                        @if ($hasCsnDate)
                            <button type="button" wire:click="clearCsnDate" class="ow-btn-link">Clear CSN date</button>
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
                                    wire:key="ow-day-{{ $day['date'] }}"
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
                                {{-- written out: Str::plural('CSN') follows the word's case and gives "CSNS" --}}
                                <span class="ow-day-count">{{ $day['count'] === 0 ? 'No CSN' : $day['count'].' CSN'.($day['count'] === 1 ? '' : 's') }}</span>
                            </button>
                        @endforeach
                    </div>

                    <button type="button" class="ow-strip-nav" wire:click="shiftStrip(7)" aria-label="Next 7 days">
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
