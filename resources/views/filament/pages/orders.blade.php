@php
    $data = $this->getOrders();
    $rows = $data['rows'];
    $cards = $this->cards($data['summary']);
    $allColumns = $this->columns();
    $columns = $this->visibleColumns();
    $shown = array_column($columns, 'key');
    $show = fn (string $key): bool => in_array($key, $shown, true);
    $stripDays = $this->stripDays();
    $hasCreatedDate = filled($createdFrom) || filled($createdTo);
    $colFields = \App\Support\OrderListingData::COLUMN_FILTERS;
@endphp

<x-filament-panels::page class="ow-page">
    {{-- Header --}}
    <div class="ow-head">
        <div>
            <div class="ow-crumb">Operations / Orders</div>
            <h1 class="ow-title">Order management</h1>
        </div>
        <div class="ow-actions">
            {{-- OCR quotation processing (no menu item since 10 Oct 2026) --}}
            <a href="{{ \App\Filament\Resources\OcrUploadResource::getUrl('index') }}" class="ow-btn">OCR quotation processing</a>
            <a href="{{ $this->createUrl() }}" class="ow-btn ow-btn-primary">+ Create order for customer</a>
        </div>
    </div>

    <div class="ow-stack">
        {{-- Summary cards --}}
        <div class="ow-cards">
            @foreach ($cards as $card)
                <button type="button"
                        wire:click="selectCard('{{ $card['key'] }}')"
                        @class([
                            'ow-stat',
                            'ow-stat-'.$card['key'] => $card['key'] !== '',
                            'ow-stat-active' => ($this->card ?? '') === $card['key'],
                        ])>
                    <div class="ow-stat-label">{{ $card['label'] }}</div>
                    <div class="ow-stat-value">{{ $card['count'] }}</div>
                    <div class="ow-stat-hint">{{ $card['hint'] }}</div>
                </button>
            @endforeach
        </div>

        {{-- Order stage tags: click one to filter the table --}}
        <div class="ow-tags" role="group" aria-label="Filter by order stage">
            @foreach ($this->stageTags($data['stage_counts']) as $tag)
                <button type="button"
                        wire:key="ow-stage-{{ $tag['key'] ?: 'all' }}"
                        wire:click="selectStage('{{ $tag['key'] }}')"
                        aria-pressed="{{ $this->stage === $tag['key'] ? 'true' : 'false' }}"
                        @class([
                            'ow-tag',
                            'ow-tag-'.$tag['color'],
                            'ow-tag-active' => $this->stage === $tag['key'],
                            'ow-tag-empty' => $tag['count'] === 0,
                        ])>
                    <span>{{ $tag['label'] }}</span>
                    <span class="ow-tag-count">{{ $tag['count'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Filters + table --}}
        <div class="ow-card">
            <div class="ow-filters">
                <div class="ow-filter-top">
                    <div class="ow-field ow-search">
                        <label for="ow-search">Search</label>
                        <input type="search"
                               id="ow-search"
                               wire:model.live.debounce.400ms="search"
                               class="ow-input"
                               placeholder="Search order, enquiry, customer or DO…">
                    </div>
                    {{-- The two date ranges are used most, so they are always shown (not behind "Filters +") --}}
                    <div class="ow-field">
                        <label>Order created date</label>
                        <x-og.date-range from="createdFrom" to="createdTo" :from-value="$createdFrom" :to-value="$createdTo" label="Order created date" />
                    </div>
                    <div class="ow-field">
                        <label>Quotation valid until</label>
                        <x-og.date-range from="validFrom" to="validTo" :from-value="$validFrom" :to-value="$validTo" label="Quotation valid until" />
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
                        <label>Customer type</label>
                        <select wire:model.live="customerType" class="ow-select">
                            @foreach ($this->customerTypeOptions() as $value => $label)
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
                        <label>Salesperson</label>
                        <select wire:model.live="salesperson" class="ow-select">
                            @foreach ($this->salespersonOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($filtersOpen)
                    <div class="ow-fgrid">
                        <div class="ow-field">
                            <label>Payment status</label>
                            <select wire:model.live="paymentStatus" class="ow-select">
                                @foreach ($this->paymentStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Payment method</label>
                            <select wire:model.live="paymentMethod" class="ow-select">
                                @foreach ($this->paymentMethodOptions() as $value => $label)
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
                            <label>Pricing source</label>
                            <select wire:model.live="pricingSource" class="ow-select">
                                @foreach ($this->pricingSourceOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ow-fgrid">
                        <div class="ow-field">
                            <label>Quotation status</label>
                            <select wire:model.live="quotationStatus" class="ow-select">
                                @foreach ($this->quotationStatusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Drop-off type</label>
                            <select wire:model.live="dropOffType" class="ow-select">
                                @foreach ($this->dropOffTypeOptions() as $value => $label)
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

            {{-- Created-date strip: one card per day with its order count (other filters apply); click a day to show only that day --}}
            <section class="ow-orders-strip" aria-label="Order created date">
                <div class="ow-strip-head">
                    <span class="ow-strip-title">Order created date <span class="ow-strip-range">· {{ $this->stripRangeLabel() }}</span></span>
                    <span class="ow-strip-links">
                        @unless ($this->stripShowsToday())
                            <button type="button" wire:click="stripToday" class="ow-btn-link">Today</button>
                        @endunless
                        @if ($hasCreatedDate)
                            <button type="button" wire:click="clearCreatedDate" class="ow-btn-link">Clear created date</button>
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
                                    @class(['ow-day', 'is-selected' => $day['selected'], 'is-today' => $day['today'], 'is-empty' => $day['count'] === 0])>
                                <span class="ow-day-date">
                                    @if ($day['today'])
                                        <span class="ow-day-dot" aria-hidden="true"></span><span class="ow-sr">Today,</span>
                                    @endif
                                    {{ $day['label'] }}
                                </span>
                                <span class="ow-day-count">{{ $day['count'] === 0 ? 'No order' : $day['count'].' order'.($day['count'] === 1 ? '' : 's') }}</span>
                            </button>
                        @endforeach
                    </div>
                    <button type="button" class="ow-strip-nav" wire:click="shiftStrip(7)" aria-label="Next 7 days">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M7.5 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
            </section>

            <div class="ow-table-wrap">
                <table class="ow-table ow-orders-table">
                    {{-- Order / Customer carries the most information, so it gets the widest column --}}
                    <colgroup>
                        @foreach ($columns as $column)
                            <col class="ow-col-{{ ['salesperson' => 'sp'][$column['key']] ?? $column['key'] }}">
                        @endforeach
                        <col class="ow-col-toggle">
                    </colgroup>
                    <thead>
                        {{-- Click a header to sort (ascending, descending, newest first); the funnel opens an Excel-style filter for that column --}}
                        <tr>
                            @foreach ($columns as $column)
                                @php
                                    $sorted = $sort === $column['key'];
                                    $filterFields = collect($colFields[$column['key']] ?? [])->map(fn ($label, $field) => [
                                        'key' => $field,
                                        'label' => $label,
                                        'values' => $data['column_options'][$field] ?? [],
                                        'selected' => $data['column_filters'][$field] ?? null,
                                    ])->values()->all();
                                    $filtered = $this->columnFiltered($column['key']);
                                @endphp
                                <th wire:key="ow-th-{{ $column['key'] }}"
                                    @class(['ow-num' => $column['num'], 'ow-th-sorted' => $sorted, 'ow-th-filtered' => $filtered])
                                    @if ($sorted) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                    <div class="ow-th-inner">
                                        <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="ow-th-sort">
                                            {{ $column['label'] }}
                                            <span class="ow-sort-ind" aria-hidden="true">{{ $sorted ? ($dir === 'desc' ? '▼' : '▲') : '↕' }}</span>
                                        </button>
                                        @if ($column['key'] === 'date')
                                            {{-- Order date: the date range picker (same as "Order created date" in the filter card) --}}
                                            <x-og.date-range from="createdFrom" to="createdTo" :from-value="$createdFrom" :to-value="$createdTo" label="Order date" placeholder="Any date" class="ow-cf-dr" />
                                        @else
                                        <div class="ow-cf"
                                             x-data="ogColumnFilter(@js($column['key']), @js($column['key'] === 'amount'))"
                                             data-fields="{{ json_encode($filterFields) }}"
                                             data-min="{{ $amountMin }}" data-max="{{ $amountMax }}"
                                             x-on:keydown.escape.window="open = false"
                                             x-on:scroll.window.passive="if (open) place()"
                                             x-on:resize.window="if (open) place()">
                                            <button type="button" x-ref="trigger" x-on:click="toggleMenu()" @class(['ow-cf-btn', 'is-active' => $filtered])
                                                    title="Filter {{ $column['label'] }}" aria-label="Filter {{ $column['label'] }}" x-bind:aria-expanded="open.toString()">
                                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd"/></svg>
                                            </button>
                                            <template x-teleport="body">
                                                <div class="ow-cf-panel" x-show="open" x-cloak x-transition.opacity.duration.100ms
                                                     x-bind:style="panelStyle" x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false"
                                                     role="dialog" aria-label="Filter {{ $column['label'] }}">
                                                    <div class="ow-cf-sort">
                                                        <button type="button" x-on:click="sort('asc')">Sort A → Z</button>
                                                        <button type="button" x-on:click="sort('desc')">Sort Z → A</button>
                                                    </div>
                                                    <template x-for="field in fields" x-bind:key="field.key">
                                                        <div class="ow-cf-field">
                                                            <div class="ow-cf-label" x-text="field.label"></div>
                                                            <input type="search" class="ow-cf-search" placeholder="Search" x-model="query[field.key]">
                                                            <div class="ow-cf-list">
                                                                <label class="ow-cf-item ow-cf-all">
                                                                    <input type="checkbox" x-bind:checked="allTicked(field)" x-bind:indeterminate="someTicked(field)" x-on:change="toggleAll(field, $event.target.checked)">
                                                                    <span>(Select all)</span>
                                                                </label>
                                                                <template x-for="item in visible(field)" x-bind:key="item.value">
                                                                    <label class="ow-cf-item">
                                                                        <input type="checkbox" x-bind:checked="ticked[field.key].has(item.value)" x-on:change="tick(field, item.value)">
                                                                        <span class="ow-cf-value" x-text="item.value"></span>
                                                                        <span class="ow-cf-count" x-text="item.count"></span>
                                                                    </label>
                                                                </template>
                                                                <p class="ow-cf-none" x-show="visible(field).length === 0">No values</p>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="isAmount">
                                                        <div class="ow-cf-field">
                                                            <div class="ow-cf-label">Amount (RM)</div>
                                                            <div class="ow-cf-range">
                                                                <input type="number" min="0" step="0.01" placeholder="Min" x-model="min">
                                                                <span>–</span>
                                                                <input type="number" min="0" step="0.01" placeholder="Max" x-model="max">
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div class="ow-cf-foot">
                                                        <button type="button" class="ow-btn-link" x-on:click="clear()">Clear filter</button>
                                                        <span class="ow-cf-foot-right">
                                                            <button type="button" class="ow-btn ow-btn-sm" x-on:click="open = false">Cancel</button>
                                                            <button type="button" class="ow-btn ow-btn-sm ow-btn-primary" x-on:click="apply()">OK</button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                            {{-- column toggle: show / hide columns (kept for the session) --}}
                            <th class="ow-th-toggle"
                                x-data="{ open: false, style: '', place() { const r = this.$refs.btn.getBoundingClientRect(); this.style = `top:${Math.round(r.bottom + 6)}px;left:${Math.round(Math.max(8, r.right - 240))}px;width:240px`; } }"
                                x-on:keydown.escape.window="open = false" x-on:scroll.window.passive="if (open) place()" x-on:resize.window="if (open) place()">
                                <button type="button" x-ref="btn" x-on:click="place(); open = ! open" @class(['ow-cf-btn', 'is-active' => $hiddenColumns !== []]) title="Show / hide columns" aria-label="Show / hide columns" x-bind:aria-expanded="open.toString()">
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 4.75A.75.75 0 012.75 4h3.5a.75.75 0 01.75.75v10.5a.75.75 0 01-.75.75h-3.5a.75.75 0 01-.75-.75V4.75zM8.25 4a.75.75 0 00-.75.75v10.5c0 .414.336.75.75.75h3.5a.75.75 0 00.75-.75V4.75a.75.75 0 00-.75-.75h-3.5zM13.75 4a.75.75 0 00-.75.75v10.5c0 .414.336.75.75.75h3.5a.75.75 0 00.75-.75V4.75a.75.75 0 00-.75-.75h-3.5z"/></svg>
                                </button>
                                <div class="ow-cf-panel ow-toggle-panel" x-show="open" x-cloak x-bind:style="style" x-transition.opacity.duration.100ms x-on:click.outside="if (! $refs.btn.contains($event.target)) open = false" role="dialog" aria-label="Columns">
                                    <div class="ow-cf-label">Columns</div>
                                    @foreach ($allColumns as $column)
                                        <label class="ow-cf-item" wire:key="ow-toggle-{{ $column['key'] }}">
                                            <input type="checkbox" @checked($show($column['key'])) @disabled($column['key'] === 'order') wire:click="toggleColumn('{{ $column['key'] }}')">
                                            <span class="ow-cf-value">{{ $column['label'] }}</span>
                                        </label>
                                    @endforeach
                                    @if ($hiddenColumns !== [])
                                        <div class="ow-cf-foot"><button type="button" class="ow-btn-link" wire:click="showAllColumns">Show all columns</button></div>
                                    @endif
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="ow-row-{{ $row['kind'] }}-{{ $row['id'] }}"
                                class="ow-row ow-row-{{ $row['stage']['color'] }}"
                                x-on:click="if (! $event.target.closest('a, button')) window.location.href = @js($row['url'])">
                                <td>
                                    <a href="{{ $row['url'] }}" class="ow-order-no">{{ $row['order_number'] }}</a>
                                    <div class="ow-l2">
                                        {{ $row['customer'] }}
                                        @if ($row['customer_type'])
                                            <span class="ow-ctype ow-ctype-{{ $row['customer_type']['key'] }}" title="Customer type">{{ $row['customer_type']['label'] }}</span>
                                        @endif
                                    </div>
                                    <div class="ow-l3">
                                        {{ $row['document_number'] ?? $row['enquiry_ref'] }}@if ($row['order_type']) · {{ $row['order_type'] }}@endif
                                        @if ($row['version'] && $row['version'] > 1) · v{{ $row['version'] }}@endif
                                    </div>
                                    @if ($row['kind'] === 'enquiry')
                                        <div class="ow-l3">{{ $row['source_line'] }}</div>
                                    @endif
                                </td>
                                @if ($show('date'))
                                <td>
                                    <div>{{ $row['created_at']?->format('d/m/Y') ?? '—' }}</div>
                                    <div class="ow-l3">{{ $row['created_at']?->format('h:i A') }}</div>
                                </td>
                                @endif
                                @if ($show('route'))
                                <td>
                                    <div>{{ $row['route_from'] }}</div>
                                    <div>→ {{ $row['route_to'] }}</div>
                                    @if ($row['consignee'] && $row['consignee'] !== $row['route_to'])
                                        <div class="ow-l3">{{ $row['consignee'] }}</div>
                                    @endif
                                </td>
                                @endif
                                @if ($show('salesperson'))
                                <td>
                                    @if ($row['salesperson'])
                                        {{ $row['salesperson'] }}
                                    @else
                                        <span class="ow-pill ow-pill-action ow-pill-plain">No salesperson</span>
                                    @endif
                                </td>
                                @endif
                                @if ($show('service'))
                                <td>{{ $row['service_type'] ?: '—' }}</td>
                                @endif
                                @if ($show('stage'))
                                <td>
                                    <span class="ow-pill ow-pill-{{ $row['stage']['color'] }}">{{ $row['stage']['label'] }}</span>
                                    <div class="ow-l2">{{ $row['stage']['hint'] }}</div>
                                </td>
                                @endif
                                @if ($show('payment'))
                                <td>
                                    <span class="ow-pill ow-pill-{{ $row['payment']['color'] }}">{{ $row['payment']['label'] }}</span>
                                    @if ($row['payment']['method'])
                                        <div class="ow-l2">{{ $row['payment']['method'] }}</div>
                                    @endif
                                    @if ($row['payment']['hint'] && $row['payment']['hint'] !== $row['payment']['method'])
                                        <div class="ow-l3">{{ $row['payment']['hint'] }}</div>
                                    @endif
                                </td>
                                @endif
                                @if ($show('amount'))
                                <td class="ow-num">
                                    <div @class(['ow-l1', 'ow-l2' => $row['amount_muted']])>{{ $row['amount'] }}</div>
                                    @if ($row['order_type'])
                                        <div class="ow-l3">{{ $row['order_type'] }}</div>
                                    @endif
                                </td>
                                @endif
                                @if ($show('next'))
                                <td>
                                    <a href="{{ $row['next_url'] }}" class="ow-next">{{ $row['next_step'] }} →</a>
                                </td>
                                @endif
                                <td class="ow-td-toggle"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + 1 }}" class="ow-empty">No orders match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ow-foot">
                <span>{{ $data['count'] }} of {{ $data['total'] }} {{ \Illuminate\Support\Str::plural('record', $data['total']) }}</span>
                <span>All results on one page</span>
            </div>
        </div>

        <div class="ow-flow">{{ implode(' → ', $this->steps()) }}</div>
    </div>

    {{-- Excel-style column filter: the checklists come from data-fields (kept fresh by Livewire), read each time the menu opens --}}
    <script>
        window.ogColumnFilter = window.ogColumnFilter || function (column, isAmount) {
            return {
                column, isAmount, open: false, fields: [], ticked: {}, query: {}, min: '', max: '', panelStyle: '',
                toggleMenu() {
                    if (this.open) { this.open = false; return; }
                    // $root: the filter container (holds the data-* attributes); state first, then the fields the menu renders
                    const root = this.$root;
                    const fields = JSON.parse(root.dataset.fields || '[]');
                    const ticked = {}; const query = {};
                    fields.forEach((f) => {
                        const all = f.values.map((v) => v.value);
                        ticked[f.key] = new Set(Array.isArray(f.selected) ? f.selected.filter((v) => all.includes(v)) : all);
                        query[f.key] = '';
                    });
                    this.ticked = ticked; this.query = query; this.fields = fields;
                    this.min = root.dataset.min || ''; this.max = root.dataset.max || '';
                    this.place();
                    this.open = true;
                },
                // under the funnel, kept inside the window (re-run on scroll / resize while open)
                place() {
                    const r = this.$refs.trigger.getBoundingClientRect();
                    const width = 300;
                    const left = Math.max(8, Math.min(r.left - 8, window.innerWidth - width - 8));
                    const top = Math.round(r.bottom + 6);
                    const maxHeight = Math.max(220, window.innerHeight - top - 12);
                    this.panelStyle = `top:${top}px;left:${Math.round(left)}px;width:${width}px;max-height:${maxHeight}px`;
                },
                visible(f) {
                    const q = (this.query[f.key] || '').toLowerCase();
                    return q === '' ? f.values : f.values.filter((v) => v.value.toLowerCase().includes(q));
                },
                allTicked(f) { return this.visible(f).every((v) => this.ticked[f.key].has(v.value)); },
                someTicked(f) { const vis = this.visible(f); const n = vis.filter((v) => this.ticked[f.key].has(v.value)).length; return n > 0 && n < vis.length; },
                toggleAll(f, on) { this.visible(f).forEach((v) => on ? this.ticked[f.key].add(v.value) : this.ticked[f.key].delete(v.value)); this.ticked = { ...this.ticked }; },
                tick(f, value) { const set = this.ticked[f.key]; set.has(value) ? set.delete(value) : set.add(value); this.ticked = { ...this.ticked }; },
                apply() {
                    const payload = {};
                    this.fields.forEach((f) => {
                        const set = this.ticked[f.key];
                        payload[f.key] = set.size === f.values.length ? null : [...set];
                    });
                    this.open = false;
                    if (this.isAmount) { this.$wire.set('amountMin', this.min === null ? '' : String(this.min), false); this.$wire.set('amountMax', this.max === null ? '' : String(this.max), false); }
                    this.$wire.applyColumnFilter(this.column, payload);
                },
                clear() {
                    this.open = false;
                    if (this.isAmount) { this.$wire.set('amountMin', '', false); this.$wire.set('amountMax', '', false); }
                    this.$wire.clearColumnFilter(this.column);
                },
                sort(dir) { this.open = false; this.$wire.sortColumn(this.column, dir); },
            };
        };
    </script>
</x-filament-panels::page>
