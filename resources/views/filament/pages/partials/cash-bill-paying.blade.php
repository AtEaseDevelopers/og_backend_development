{{-- Cash Bill Payment: the part of the amount received that goes to this item (less than its due = partial) --}}
<td class="cb-num">
    <input type="text" inputmode="decimal" autocomplete="off"
        wire:model.live.debounce.500ms="allocations.{{ $key }}"
        x-on:input="const v = $el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1'); if (v !== $el.value) $el.value = v"
        class="cb-pay-input" aria-label="Paying for this item">
    @if ($plan['errors'][$key] ?? null)
        <div class="cb-pay-error">{{ $plan['errors'][$key] }}</div>
    @elseif (($plan['amounts'][$key] ?? 0) > 0 && ($plan['amounts'][$key] ?? 0) < $due - 0.004)
        <div class="cb-paid-note">partial · RM {{ number_format($due - $plan['amounts'][$key], 2) }} left</div>
    @elseif (($plan['amounts'][$key] ?? 0) <= 0)
        <div class="cb-paid-note">not paid now</div>
    @endif
</td>
