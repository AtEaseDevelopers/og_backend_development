{{-- The consignor / consignee of a block (user, 26 Oct 2026: one field, no separate name box): a saved one from
     Master Data → Consignor / Consignee (the customers are among them) fills the name, address, PIC, telephone and
     area; "+ New consignor / consignee" adds one there and picks it. The customer's tagged ones come first. --}}
@php
    $current = trim((string) ($current ?? ''));
    $hasPicked = filled($partyId ?? null);
@endphp
<div class="ow-field">
    <div class="ow-label-row">
        <label>{{ ucfirst($role) }}</label>
        @unless ($locked ?? false)
            <button type="button" wire:click="mountAction('createParty', { order: {{ (int) ($oi ?? 0) }}, pair: {{ (int) ($pi ?? 0) }}, role: '{{ $role }}' })" class="ow-btn-link">+ New {{ $role }}</button>
        @endunless
    </div>
    <select wire:model.live="{{ $model }}" class="ow-select" @disabled($locked ?? false)>
        {{-- a name kept on the order without a saved record (an older order): shown as the current choice --}}
        <option value="">{{ ! $hasPicked && $current !== '' ? $current : '— Select '.$role.' —' }}</option>
        @if (($parties['tagged'] ?? []) !== [])
            <optgroup label="{{ $customerName ?: 'This customer' }}">
                @foreach ($parties['tagged'] as $id => $party)
                    <option value="{{ $id }}" @if ($party['tag'] !== '') data-og-tag="{{ $party['tag'] }}" @endif @if ($party['sub'] !== '') data-og-sub="{{ $party['sub'] }}" @endif>{{ $party['label'] }}</option>
                @endforeach
            </optgroup>
        @endif
        @if (($parties['others'] ?? []) !== [])
            <optgroup label="{{ ($parties['tagged'] ?? []) !== [] ? 'Others' : ucfirst($role).'s' }}">
                @foreach ($parties['others'] as $id => $party)
                    <option value="{{ $id }}" @if ($party['tag'] !== '') data-og-tag="{{ $party['tag'] }}" @endif @if ($party['sub'] !== '') data-og-sub="{{ $party['sub'] }}" @endif>{{ $party['label'] }}</option>
                @endforeach
            </optgroup>
        @endif
    </select>
</div>
