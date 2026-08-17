@php
    $special = $history['special'] ?? [];
    $previous = $history['previous'] ?? [];
@endphp

@if(empty($customerId))
    <div style="font-size:12px;color:#6b7280;padding:8px 0;">Select a customer to view special prices and previous quotation rates.</div>
@else
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px;">
        <div>
            <div style="font-weight:600;margin-bottom:6px;">Special prices</div>
            <div style="max-height:140px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead style="background:#f9fafb;position:sticky;top:0;">
                        <tr>
                            <th style="text-align:left;padding:4px 6px;">Item</th>
                            <th style="text-align:left;padding:4px 6px;">Dest.</th>
                            <th style="text-align:right;padding:4px 6px;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($special as $row)
                            <tr style="border-top:1px solid #f3f4f6;">
                                <td style="padding:4px 6px;">{{ $row['item'] }}</td>
                                <td style="padding:4px 6px;">{{ $row['destination'] }}</td>
                                <td style="padding:4px 6px;text-align:right;">RM {{ number_format($row['price'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="padding:8px 6px;color:#9ca3af;">No special prices on file.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <div style="font-weight:600;margin-bottom:6px;">Previous quotation prices</div>
            <div style="max-height:140px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead style="background:#f9fafb;position:sticky;top:0;">
                        <tr>
                            <th style="text-align:left;padding:4px 6px;">Item</th>
                            <th style="text-align:left;padding:4px 6px;">Dest.</th>
                            <th style="text-align:right;padding:4px 6px;">Price</th>
                            <th style="text-align:left;padding:4px 6px;">Quote</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($previous as $row)
                            <tr style="border-top:1px solid #f3f4f6;">
                                <td style="padding:4px 6px;">{{ $row['item'] }}</td>
                                <td style="padding:4px 6px;">{{ $row['destination'] }}</td>
                                <td style="padding:4px 6px;text-align:right;">RM {{ number_format($row['price'], 2) }}</td>
                                <td style="padding:4px 6px;white-space:nowrap;">{{ $row['quote'] }}<br><span style="color:#9ca3af;">{{ $row['date'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="padding:8px 6px;color:#9ca3af;">No previous quotation prices.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="font-size:11px;color:#6b7280;margin-top:6px;">Rates auto-fill from special price first, then previous quote, then standard master price.</div>
@endif
