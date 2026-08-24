@php
    $quantity = $summary['quantity'] ?? 1;
    $destinations = $summary['destinations'] ?? [];
@endphp

<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50">
    <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <span class="font-medium text-gray-950 dark:text-white">Range pricing</span>
        <span class="text-gray-600 dark:text-gray-400">
            Qty entered: <strong class="text-gray-950 dark:text-white">{{ number_format($quantity, 0) }}</strong>
        </span>
    </div>

    @if ($destinations === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">No tier data for this UOM.</p>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($destinations as $destination)
                <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium text-gray-950 dark:text-white">{{ $destination['name'] }}</div>
                            @if ($destination['active_range'])
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Tier {{ $destination['active_range'] }}
                                </div>
                            @endif
                        </div>
                        @if ($destination['unit_price'] !== null)
                            <div class="text-right">
                                <div class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                    RM {{ number_format($destination['unit_price'], 2) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">per unit</div>
                            </div>
                        @endif
                    </div>

                    @if (($destination['tiers'] ?? []) !== [])
                        <div class="space-y-1 border-t border-gray-100 pt-2 dark:border-gray-800">
                            @foreach ($destination['tiers'] as $tier)
                                <div @class([
                                    'flex items-center justify-between rounded px-2 py-1 text-xs',
                                    'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-300' => $tier['active'],
                                    'text-gray-600 dark:text-gray-400' => ! $tier['active'],
                                ])>
                                    <span>Qty {{ $tier['range'] }}</span>
                                    <span>RM {{ number_format($tier['price'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">No tiers configured.</p>
                    @endif

                    @if ($destination['line_total'] !== null && $quantity !== 1.0)
                        <div class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-600 dark:border-gray-800 dark:text-gray-400">
                            Line total:
                            <strong class="text-gray-950 dark:text-white">
                                RM {{ number_format($destination['line_total'], 2) }}
                            </strong>
                            ({{ number_format($quantity, 0) }} × RM {{ number_format($destination['unit_price'], 2) }})
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
