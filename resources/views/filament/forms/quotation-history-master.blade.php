<div class="qp-ref-card qp-ref-card--default">
    <div class="qp-ref-card-title">System Default Pricing</div>
    <div class="qp-ref-table-wrap">
        <table class="qp-ref-table">
            <thead>
                <tr>
                    <th>Measurement</th>
                    <th class="qp-ref-num">Qty</th>
                    @foreach ($destinations as $destination)
                        <th class="qp-ref-num">{{ $destination }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['measurement'] }}</td>
                        <td class="qp-ref-num">{{ number_format($row['qty'], 0) }}</td>
                        @foreach ($destinations as $destination)
                            <td class="qp-ref-num qp-ref-nowrap">
                                @php $price = $row['prices'][$destination] ?? null @endphp
                                @if($price !== null)
                                    RM {{ number_format($price, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(2, count($destinations) + 2) }}" class="qp-ref-empty-cell">No default master prices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
