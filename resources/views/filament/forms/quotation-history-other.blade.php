<div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-2 font-semibold text-gray-950 dark:text-white">Other Non - Default Price</div>
    <div class="max-h-48 overflow-auto">
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
                @forelse($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1.5 pr-2 whitespace-nowrap">{{ $row['date'] ?? '—' }}</td>
                        <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                        <td class="py-1.5 pr-2">{{ number_format($row['qty'], 0) }}</td>
                        <td class="py-1.5 pr-2 text-right">RM {{ number_format($row['price'], 2) }}</td>
                        <td class="py-1.5">
                            @if($row['view_url'] ?? null)
                                <a href="{{ $row['view_url'] }}" target="_blank" class="text-primary-600 hover:underline">View</a>
                            @else
                                {{ $row['quote'] ?? '—' }}
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
