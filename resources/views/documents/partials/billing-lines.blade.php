{{-- the table of every billing document — Proforma Invoice, Cash Bill, Invoice, COD Invoice (client, 16 Nov 2026):
     No., Date, CSN, D/O, From, To, Consignee, Item, Amount; one row for one consignment note, its D/O numbers and its
     goods on one line --}}
<table class="items inv-items">
    <thead>
        <tr>
            <th class="inv-col-no">No.</th>
            <th class="inv-col-date">Date</th>
            <th class="inv-col-csn">CSN</th>
            <th class="inv-col-ref">D/O</th>
            <th class="inv-col-area">From</th>
            <th class="inv-col-area">To</th>
            <th class="inv-col-consignee">Consignee</th>
            <th class="inv-col-goods">Item</th>
            <th class="inv-col-amt">Amount (RM)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="inv-col-no">{{ $loop->iteration }}</td>
                <td class="inv-col-date">{{ $line['date'] ?? '—' }}</td>
                <td class="inv-col-csn">{{ $line['csn_number'] ?? '—' }}</td>
                <td class="inv-col-ref">{{ implode(', ', $line['your_ref'] ?? []) ?: '—' }}</td>
                <td class="inv-col-area">{{ $line['from'] ?? '—' }}</td>
                <td class="inv-col-area">{{ $line['to'] ?? '—' }}</td>
                <td class="inv-col-consignee">{{ $line['consignee'] ?? '—' }}</td>
                <td class="inv-col-goods">{{ implode(', ', $line['items'] ?? []) ?: '—' }}</td>
                <td class="inv-col-amt">{{ $line['amount'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="padding: 10px 5px; text-align: center; color: #666;">No lines.</td>
            </tr>
        @endforelse
    </tbody>
</table>
