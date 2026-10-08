{{--
    One field for a date range: click it, pick the start day then the end day (public/js/og/date-range-picker.js).
    Usage: <x-og.date-range from="createdFrom" to="createdTo" :from-value="$createdFrom" :to-value="$createdTo" label="Order created date" />
    "from" / "to" are the Livewire properties (Y-m-d strings) the two ends are bound to.
    One day instead of a range: <x-og.date-range single from="scheduleDate" :from-value="$scheduleDate" label="Schedule date" />
    Filter bars with an "Apply" button: :live="false" binds with plain wire:model (sent with the next request).
--}}
@props([
    'from',
    'to' => null,
    'single' => false,
    'live' => true,
    'fromValue' => null,
    'toValue' => null,
    'label' => 'Date range',
    'placeholder' => null,
])

@php
    $parse = fn ($value) => filled($value) ? rescue(fn () => \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', (string) $value), null, false) : null;
    $start = $parse($fromValue);
    $end = $parse($toValue);
    $format = fn ($date) => $date->format('j M Y');

    $placeholder ??= $single ? 'Pick a date' : 'Any date';
    $model = $live ? 'wire:model.live' : 'wire:model';

    $text = match (true) {
        $single => $start ? $format($start) : null,
        $start && $end && $start->isSameDay($end) => $format($start),
        $start && $end => $format($start).' – '.$format($end),
        (bool) $start => 'From '.$format($start),
        (bool) $end => 'Until '.$format($end),
        default => null,
    };
@endphp

<div {{ $attributes->class(['og-dr', 'has-value' => $text !== null]) }} data-og-daterange data-label="{{ $label }}" @if ($single) data-og-daterange-mode="single" @endif>
    <button type="button" class="og-dr-trigger" data-og-daterange-trigger aria-haspopup="dialog" aria-expanded="false" aria-label="{{ $label }}{{ $text ? ': '.$text : '' }}">
        <svg class="og-dr-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <rect x="3" y="4.5" width="14" height="12.5" rx="2" />
            <path d="M3 8.5h14M7 2.75v3.5M13 2.75v3.5" stroke-linecap="round" />
        </svg>
        <span @class(['og-dr-text', 'is-placeholder' => $text === null]) data-og-daterange-text data-placeholder="{{ $placeholder }}">{{ $text ?? $placeholder }}</span>
    </button>

    @if ($text !== null)
        <button type="button" class="og-dr-clear" data-og-daterange-clear aria-label="Clear {{ strtolower($label) }}">×</button>
    @else
        <svg class="og-dr-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    @endif

    <input type="hidden" {{ $model }}="{{ $from }}" value="{{ $fromValue }}" data-og-daterange-from>
    @unless ($single)
        <input type="hidden" {{ $model }}="{{ $to }}" value="{{ $toValue }}" data-og-daterange-to>
    @endunless
</div>
