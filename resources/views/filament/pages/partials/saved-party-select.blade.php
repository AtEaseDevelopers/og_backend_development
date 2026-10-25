{{-- A saved consignor / consignee (Master Data → Consignor / Consignee): picking one fills the block's name, address, PIC,
     telephone and area. The customer's tagged ones come first; customers carry a "Customer" tag and the address below. --}}
<div class="ow-field">
    <label>Saved {{ $role }}</label>
    <select wire:model.live="{{ $model }}" class="ow-select" @disabled($locked ?? false)>
        @if (($parties['tagged'] ?? []) === [] && ($parties['others'] ?? []) === [])
            <option value="">None saved yet · add them in Master Data → Consignor / Consignee</option>
        @else
            <option value="">— Pick a saved {{ $role }} (optional) —</option>
            @if (($parties['tagged'] ?? []) !== [])
                <optgroup label="{{ $customerName ?: 'This customer' }}">
                    @foreach ($parties['tagged'] as $id => $party)
                        <option value="{{ $id }}" @if ($party['tag'] !== '') data-og-tag="{{ $party['tag'] }}" @endif @if ($party['sub'] !== '') data-og-sub="{{ $party['sub'] }}" @endif>{{ $party['label'] }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if (($parties['others'] ?? []) !== [])
                <optgroup label="{{ ($parties['tagged'] ?? []) !== [] ? 'Others' : 'Saved '.$role.'s' }}">
                    @foreach ($parties['others'] as $id => $party)
                        <option value="{{ $id }}" @if ($party['tag'] !== '') data-og-tag="{{ $party['tag'] }}" @endif @if ($party['sub'] !== '') data-og-sub="{{ $party['sub'] }}" @endif>{{ $party['label'] }}</option>
                    @endforeach
                </optgroup>
            @endif
        @endif
    </select>
</div>
