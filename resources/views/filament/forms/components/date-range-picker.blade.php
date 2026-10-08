{{-- App\Filament\Forms\Components\DateRangePicker: the shared range picker bound to "{statePath}.from" / "{statePath}.until". --}}
@php
    $statePath = $getStatePath();
    $state = $getState();
    $state = is_array($state) ? $state : [];
    // Live, debounced or on-blur forms all send at once (the hidden inputs never blur); deferred forms wait for the next request
    $isLive = $getStateBindingModifiers() !== [];
    $label = strip_tags((string) ($getLabel() ?? '')) ?: 'Date range';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-og.date-range
        :from="$statePath.'.from'"
        :to="$statePath.'.until'"
        :from-value="$state['from'] ?? null"
        :to-value="$state['until'] ?? null"
        :live="$isLive"
        :label="$label"
        :placeholder="$getPlaceholder()"
        :id="$getId()"
    />
</x-dynamic-component>
