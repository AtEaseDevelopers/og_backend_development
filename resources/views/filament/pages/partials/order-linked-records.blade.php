{{--
    Linked records card (order overview, bottom of the right column; replaces the former Documents tab):
    quotation, proforma, invoice / cash bill, CSN and DO (page + PDF), refund notes, the files uploaded with the
    order, other records under the same order number, and the invoice / billing buttons.
--}}
<div class="ow-card ow-card-pad ow-anchor" id="og-section-documents">
    <div class="ow-card-title">Linked records <span class="ow-note">{{ count($ov['linked']) }}</span></div>
    {{-- up to 10 rows visible, the rest scroll inside the card --}}
    <div class="ow-linked-list" x-data x-init="$nextTick(() => { const rows = $el.children; if (rows.length > 10) { $el.style.maxHeight = rows[10].offsetTop + 'px'; $el.classList.add('is-scrolling'); } })">
    @foreach ($ov['linked'] as $link)
        <div class="ow-linked">
            <div style="min-width:0">
                <div>
                    @if ($link['url'])
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="ow-link">{{ $link['title'] }}</a>
                    @else
                        {{ $link['title'] }}
                    @endif
                    @foreach ($link['links'] as $extra)
                        <span class="ow-note">·</span> <a href="{{ $extra['url'] }}" target="_blank" rel="noopener" class="ow-link ow-linked-extra">{{ $extra['label'] }}</a>
                    @endforeach
                </div>
                <div class="ow-note">{{ $link['sub'] }}</div>
            </div>
            <span class="ow-pill ow-pill-{{ $link['color'] }}">{{ $link['status'] }}</span>
        </div>
    @endforeach
    </div>

    @php $files = array_values(array_filter($ov['attachments'], fn (array $f) => filled($f['url']))); @endphp
    @if ($files !== [])
        <div class="ow-linked-files">
            <div class="ow-dt">Attachments ({{ count($files) }})</div>
            <div class="ow-pay-files">
                @foreach ($files as $file)
                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="ow-pay-file" title="{{ $file['name'] }} · {{ $file['source'] }}">
                        @if ($file['is_image'])
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy">
                        @else
                            <span class="ow-pay-file-ext">{{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'file') }}</span>
                        @endif
                        <span class="ow-pay-file-name">{{ $file['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($ov['other_orders'] !== [])
        <details style="margin-top:.6rem">
            <summary class="ow-link" style="cursor:pointer">Other orders from this enquiry ({{ count($ov['other_orders']) }})</summary>
            @foreach ($ov['other_orders'] as $other)
                <div class="ow-linked">
                    <div style="min-width:0"><a href="{{ $other['url'] }}" class="ow-link ow-mono">{{ $other['number'] }}</a><div class="ow-note">→ {{ $other['consignee'] }} · {{ $other['total'] }}</div></div>
                    <span class="ow-pill ow-pill-{{ $other['color'] }}">{{ $other['status'] }}</span>
                </div>
            @endforeach
        </details>
    @endif

    @if ($can['send_invoice'] || $can['generate_billing'] || ($can['issue_invoice'] ?? false))
        <div class="ow-actions" style="margin-top:.75rem">
            @if ($can['send_invoice'])
                <button type="button" wire:click="mountAction('sendInvoice')" class="ow-btn ow-btn-sm">Email document</button>
            @endif
            @if ($can['issue_invoice'] ?? false)
                <button type="button" wire:click="mountAction('issueInvoice')" class="ow-btn ow-btn-sm ow-btn-primary">Generate invoice</button>
            @endif
            @if ($can['generate_billing'])
                <button type="button" wire:click="mountAction('generateBilling')" class="ow-btn ow-btn-sm ow-btn-primary">{{ $order?->billingStatus() === \App\Enums\BillingStatus::Generated || in_array($order?->orderType(), [\App\Enums\OrderType::Cod, \App\Enums\OrderType::Term], true) ? 'Create CSN' : 'Issue cash bill → CSN' }}</button>
            @endif
        </div>
    @endif
</div>
