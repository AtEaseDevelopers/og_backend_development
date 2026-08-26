<div class="qp-ref-card qp-ref-card--special">
    <div class="qp-ref-card-title">Customer Special Pricing</div>
    <div class="qp-ref-table-wrap">
        <table class="qp-ref-table">
            <thead>
                <tr>
                    <th>Measurement</th>
                    <th>Destination</th>
                    <th>UOM</th>
                    <th>Route</th>
                    <th class="qp-ref-num">Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['measurement'] }}</td>
                        <td>{{ $row['destination'] }}</td>
                        <td>{{ $row['uom'] ?? '—' }}</td>
                        <td>{{ $row['route'] ?? '—' }}</td>
                        <td class="qp-ref-num qp-ref-nowrap">RM {{ number_format($row['price'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="qp-ref-empty-cell">No customer special prices on file.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
