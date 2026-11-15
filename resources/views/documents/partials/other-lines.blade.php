{{-- the lines of an Other Invoice (user, 16 Nov 2026): the item, the description keyed for it and the amount --}}
<table class="items inv-items">
    <thead>
        <tr>
            <th class="inv-col-no">No.</th>
            <th style="width: 28%">Item</th>
            <th>Description</th>
            <th class="inv-col-amt">Amount (RM)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="inv-col-no">{{ $loop->iteration }}</td>
                <td>{{ $line['item'] ?: '—' }}</td>
                <td>{{ $line['description'] ?: '—' }}</td>
                <td class="inv-col-amt">{{ $line['amount'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="padding: 10px 5px; text-align: center; color: #666;">No lines.</td></tr>
        @endforelse
    </tbody>
</table>
@if (filled($remarks ?? null))
    <div style="margin: 0 0 6px"><strong>Remarks :</strong> {{ $remarks }}</div>
@endif
