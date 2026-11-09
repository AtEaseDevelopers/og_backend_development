{{--
    Section 1's PIC name (user, 10 Nov 2026): a searchable dropdown of the customer's saved PICs (Master Data →
    Customers → Contact), each with its contact number under the name, as the Salesperson field; "+ Type a new PIC"
    turns it into a text box (the ↺ button goes back to the list). A customer without saved PICs gets the text box.
    Params: $model (wire:model path), $value, $typed, $savedPics, $editable, $inputId, $placeholder, $listAction, $extraClass.
--}}
@php
    $savedNames = array_column($savedPics, 'name');
@endphp
@if ($typed || $savedPics === [])
    <div class="ow-pic-typed {{ $extraClass }}">
        <input @if ($inputId) id="{{ $inputId }}" @endif type="text" wire:model.live.debounce.400ms="{{ $model }}" class="ow-input" autocomplete="off" placeholder="{{ $placeholder }}" @disabled(! $editable)>
        @if ($savedPics !== [] && $editable)
            <button type="button" wire:click="{{ $listAction }}" class="ow-pic-to-list" title="Choose a saved PIC" aria-label="Choose a saved PIC">↺</button>
        @endif
    </div>
@else
    <select @if ($inputId) id="{{ $inputId }}" @endif wire:model.live="{{ $model }}" class="ow-select {{ $extraClass }}" @disabled(! $editable)>
        <option value="">— Select PIC —</option>
        @foreach ($savedPics as $savedPic)
            <option value="{{ $savedPic['name'] }}" data-og-sub="{{ $savedPic['phone'] }}">{{ $savedPic['name'] }}</option>
        @endforeach
        @if ($value !== '' && ! in_array($value, $savedNames, true))
            <option value="{{ $value }}">{{ $value }}</option>
        @endif
        <option value="__type__">+ Type a new PIC</option>
    </select>
@endif
