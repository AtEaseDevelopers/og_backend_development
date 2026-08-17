@php
    $customerLabel = $customerName ?: 'Customer';
    $consigneeLabel = $historyDestination ?: ($destinations[0] ?? 'Consignee');

    $sourceLabel = fn (?string $source) => match ($source) {
        'special' => 'Special',
        'previous' => 'Previous',
        'master' => 'Master',
        default => '—',
    };
@endphp

<div class="space-y-4 text-sm">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Master prices --}}
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 font-semibold text-gray-950 dark:text-white">
                Price from this customer to all consignees
            </div>
            <div class="overflow-auto max-h-44">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-1.5 pr-2 font-medium">Measurement</th>
                            <th class="py-1.5 pr-2 font-medium">Qty</th>
                            <th class="py-1.5 font-medium text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($masterRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                                <td class="py-1.5 pr-2">{{ number_format($row['qty'], 0) }}</td>
                                <td class="py-1.5 text-right">
                                    @if($row['price'] !== null)
                                        RM {{ number_format($row['price'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-gray-500">No master prices for current destinations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Customer to consignee --}}
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 font-semibold text-gray-950 dark:text-white">
                {{ $customerLabel }} to {{ $consigneeLabel }}
            </div>
            <div class="overflow-auto max-h-44">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-1.5 pr-2 font-medium">Measurement</th>
                            <th class="py-1.5 pr-2 font-medium">Qty</th>
                            <th class="py-1.5 pr-2 font-medium text-right">Price (MYR)</th>
                            <th class="py-1.5 pr-2 font-medium">Source</th>
                            <th class="py-1.5 font-medium">Bill To</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routeRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                                <td class="py-1.5 pr-2">{{ number_format($row['qty'], 0) }}</td>
                                <td class="py-1.5 pr-2 text-right">
                                    @if($row['price'] !== null)
                                        RM {{ number_format($row['price'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-1.5 pr-2">
                                    <span @class([
                                        'rounded px-1.5 py-0.5 text-[10px] font-medium',
                                        'bg-amber-100 text-amber-800' => ($row['source'] ?? null) === 'special',
                                        'bg-blue-100 text-blue-800' => ($row['source'] ?? null) === 'previous',
                                        'bg-gray-100 text-gray-700' => ($row['source'] ?? null) === 'master',
                                    ])>{{ $sourceLabel($row['source'] ?? null) }}</span>
                                </td>
                                <td class="py-1.5">{{ $row['bill_to'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-gray-500">No route prices for this consignee.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Customer special prices --}}
    <div class="rounded-lg border border-amber-200 bg-amber-50/40 p-3 dark:border-amber-700 dark:bg-amber-950/20">
        <div class="mb-2 font-semibold text-gray-950 dark:text-white">Customer Special Prices</div>
        <div class="overflow-auto max-h-48">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-amber-200 dark:border-amber-800">
                        <th class="py-1.5 pr-2 font-medium">Measurement</th>
                        <th class="py-1.5 pr-2 font-medium">Destination</th>
                        <th class="py-1.5 pr-2 font-medium">UOM</th>
                        <th class="py-1.5 pr-2 font-medium">Route</th>
                        <th class="py-1.5 pr-2 font-medium text-right">Price</th>
                        <th class="py-1.5 font-medium text-right">Min charge</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($specialRows as $row)
                        <tr class="border-b border-amber-100 dark:border-amber-900/40">
                            <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                            <td class="py-1.5 pr-2">{{ $row['destination'] }}</td>
                            <td class="py-1.5 pr-2">{{ $row['uom'] }}</td>
                            <td class="py-1.5 pr-2">{{ $row['route'] }}</td>
                            <td class="py-1.5 pr-2 text-right font-medium">RM {{ number_format($row['price'], 2) }}</td>
                            <td class="py-1.5 text-right">
                                @if($row['min_charge'])
                                    RM {{ number_format($row['min_charge'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-gray-500">No special prices on file for this customer.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Other non-default prices --}}
    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-2 font-semibold text-gray-950 dark:text-white">Other Non - Default Price</div>
        <div class="overflow-auto max-h-48">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-1.5 pr-2 font-medium">Date</th>
                        <th class="py-1.5 pr-2 font-medium">Measurement</th>
                        <th class="py-1.5 pr-2 font-medium">Qty</th>
                        <th class="py-1.5 pr-2 font-medium text-right">Price</th>
                        <th class="py-1.5 font-medium">View</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($otherRows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1.5 pr-2 whitespace-nowrap">{{ $row['date'] ?? '—' }}</td>
                            <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                            <td class="py-1.5 pr-2">{{ number_format($row['qty'], 0) }}</td>
                            <td class="py-1.5 pr-2 text-right">RM {{ number_format($row['price'], 2) }}</td>
                            <td class="py-1.5">
                                @if($row['view_url'])
                                    <a href="{{ $row['view_url'] }}" target="_blank" class="text-primary-600 hover:underline">View</a>
                                @else
                                    {{ $row['quote'] }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-gray-500">No non-default prices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
