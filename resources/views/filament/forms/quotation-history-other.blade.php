<div class="qp-ref-card qp-ref-card--history">
    <div class="qp-ref-card-title">Customer History Pricing</div>
    <div class="qp-ref-table-wrap">
        <table class="qp-ref-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Measurement</th>
                    <th>Destination</th>
                    <th class="qp-ref-num">Qty</th>
                    <th class="qp-ref-num">Price</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="qp-ref-nowrap">{{ $row['date'] ?? '—' }}</td>
                        <td>{{ $row['measurement'] }}</td>
                        <td>{{ $row['destination'] ?? '—' }}</td>
                        <td class="qp-ref-num">{{ number_format($row['qty'], 0) }}</td>
                        <td class="qp-ref-num qp-ref-nowrap">RM {{ number_format($row['price'], 2) }}</td>
                        <td>
                            @if($row['view_url'] ?? null)
                                <a href="{{ $row['view_url'] }}" target="_blank" class="qp-ref-link">View</a>
                            @else
                                {{ $row['quote'] ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="qp-ref-empty-cell">No pricing history found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
