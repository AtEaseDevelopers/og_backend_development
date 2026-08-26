@php
    $customerName = $customerName ?? null;
    $destination = $destination ?? null;
    $historyRows = $historyRows ?? [];
    $specialRows = $specialRows ?? [];
    $defaultRows = $defaultRows ?? [];
    $destinations = $destinations ?? [];
@endphp

@if(! filled($customerName))
    <div class="qp-ref-empty">
        Select a consignor in <strong>Quotation Basic Details</strong> to load customer history, special pricing, and system default rates.
    </div>
@else
    <div class="qp-ref-meta">
        <span><strong>Consignor:</strong> {{ $customerName }}</span>
        @if(filled($destination))
            <span><strong>Route filter:</strong> {{ $destination }}</span>
        @endif
    </div>

    <div class="qp-ref-grid">
        <div class="qp-ref-panel qp-ref-panel--history">
            @include('filament.forms.quotation-history-other', ['rows' => $historyRows])
        </div>
        <div class="qp-ref-panel qp-ref-panel--special">
            @include('filament.forms.quotation-history-special', ['rows' => $specialRows])
        </div>
        <div class="qp-ref-panel qp-ref-panel--default">
            @include('filament.forms.quotation-history-master', [
                'rows' => $defaultRows,
                'destinations' => $destinations,
            ])
        </div>
    </div>
@endif
