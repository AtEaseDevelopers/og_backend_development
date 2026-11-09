{{-- the CSN's items (no pricing) in the order form's format, and the Remark box (client, 6–7 Nov 2026); placed before
     the consignor in the stacked layout and after the consignor / consignee table in the "consignee beside" layout --}}
<table class="csn-doc-items">
    <thead>
        <tr>
            <th class="csn-doc-no">No.</th>
            <th class="csn-doc-desc">Item</th>
            <th class="csn-doc-qty">Qty</th>
            <th class="csn-doc-uom">UOM</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="csn-doc-no">{{ $loop->iteration + ($itemOffset ?? 0) }}</td>
                <td class="csn-doc-desc">{{ ltrim((string) ($line['description'] ?? '—'), '- ') }}</td>
                <td class="csn-doc-qty">{{ $line['quantity'] ?? '—' }}</td>
                <td class="csn-doc-uom">{{ $line['uom'] ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td class="csn-doc-no">—</td>
                <td class="csn-doc-desc"><div class="csn-doc-muted">No cargo details.</div></td>
                <td class="csn-doc-qty">—</td>
                <td class="csn-doc-uom">—</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Remark (the driver note) and marking: always there, to write on when empty --}}
<table class="csn-doc-remark">
    <tr>
        <td>
            <div class="csn-doc-footer-field"><span class="csn-doc-label">Remark:</span> {{ filled($footer['remarks'] ?? null) ? $footer['remarks'] : '' }}</div>
            @if(filled($footer['marking'] ?? null))
                <div class="csn-doc-footer-field"><span class="csn-doc-label">Marking:</span> {{ $footer['marking'] }}</div>
            @endif
        </td>
    </tr>
</table>
