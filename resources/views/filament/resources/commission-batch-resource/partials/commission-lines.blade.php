{{-- Commission lines (one per CSN leg and driver): the system amount and the commission admin may change --}}
<div class="cml-wrap">
    <table class="cml-table">
        <thead>
            <tr>
                <th>CSN</th>
                <th>Date</th>
                <th>D/O No.</th>
                <th>Customer / consignee</th>
                <th>Leg · driver</th>
                <th class="cml-num">Sales amount</th>
                <th class="cml-num">System</th>
                <th class="cml-num">Commission (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php $csn = $line->consignmentNote; $do = $line->deliveryOrder; @endphp
                <tr wire:key="cml-{{ $line->id }}">
                    <td>
                        <strong>{{ $csn?->number ?? '—' }}</strong>
                        <div class="cml-sub">{{ trim(($csn?->fromLocation?->name ?? '').' → '.($csn?->toLocation?->name ?? ''), ' →') }}</div>
                    </td>
                    <td>{{ $csn?->issued_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $csn?->do_number ?: ($do?->number ?? '—') }}</td>
                    <td>
                        {{ $csn?->customer?->company_name ?: ($csn?->customer_name ?: '—') }}
                        @if ($csn?->consignee_name)<div class="cml-sub">{{ $csn->consignee_name }}</div>@endif
                    </td>
                    <td>
                        {{ $do?->parent_do_id ? 'Subsheet' : 'Main' }} · {{ str_replace('_', ' ', $line->line_type) }}
                        <div class="cml-sub">{{ $line->driver?->name ?? 'Driver removed' }}</div>
                    </td>
                    <td class="cml-num">RM {{ number_format((float) ($csn?->total_amount ?? 0), 2) }}</td>
                    <td class="cml-num">RM {{ number_format((float) ($line->system_amount ?? $line->amount), 2) }}</td>
                    <td class="cml-num">
                        @if ($editable)
                            <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.500ms="amounts.{{ $line->id }}" x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v" class="cml-input" aria-label="Commission for {{ $csn?->number }}">
                            @if (abs($this->enteredAmount($line) - (float) ($line->system_amount ?? $line->amount)) >= 0.005)
                                <div class="cml-sub cml-changed">changed</div>
                            @endif
                        @else
                            RM {{ number_format((float) $line->amount, 2) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                @php $salesTotal = (float) $lines->unique('consignment_note_id')->sum(fn ($line) => (float) ($line->consignmentNote?->total_amount ?? 0)); $commissionTotal = $lines->sum(fn ($line) => $this->enteredAmount($line)); @endphp
                <td colspan="5"><strong>{{ $lines->pluck('consignment_note_id')->unique()->count() }} CSN(s)</strong></td>
                <td class="cml-num"><strong>RM {{ number_format($salesTotal, 2) }}</strong></td>
                <td></td>
                <td class="cml-num"><strong>RM {{ number_format($commissionTotal, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>
</div>
