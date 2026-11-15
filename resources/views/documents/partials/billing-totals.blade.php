{{-- under every billing document (client, 16 Nov 2026): Transport Charges (Excluding Tax), Service Tax 6%,
     Rounding Adj., Total — with the amount in words beside --}}
<table class="inv-foot">
    <tr>
        <td class="inv-words-cell">
            @if(filled($amountInWords ?? null))<div class="inv-words">{{ $amountInWords }}</div>@endif
        </td>
        <td class="inv-totals-cell">
            <table class="inv-totals inv-totals-boxed">
                <tr>
                    <td class="inv-totals-label">{{ $totals['subtotal_label'] ?? 'Transport Charges (Excluding Tax)' }}</td>
                    <td class="inv-totals-value">{{ $totals['subtotal_excl_tax'] ?? '0.00' }}</td>
                </tr>
                <tr>
                    <td class="inv-totals-label">{{ $totals['tax_label'] ?? 'Service Tax 6%' }}</td>
                    <td class="inv-totals-value">{{ filled($totals['tax_amount'] ?? null) ? $totals['tax_amount'] : '0.00' }}</td>
                </tr>
                <tr>
                    <td class="inv-totals-label">Rounding Adj.</td>
                    <td class="inv-totals-value">{{ filled($totals['rounding'] ?? null) ? $totals['rounding'] : '0.00' }}</td>
                </tr>
                <tr class="inv-totals-grand">
                    <td class="inv-totals-label">Total</td>
                    <td class="inv-totals-value">RM {{ $totals['total'] ?? '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
