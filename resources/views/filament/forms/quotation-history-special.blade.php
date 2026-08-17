<div class="rounded-lg border border-amber-200 bg-amber-50/40 p-3 dark:border-amber-700 dark:bg-amber-950/20">
    <div class="mb-2 font-semibold text-gray-950 dark:text-white">Customer Special Prices</div>
    <div class="max-h-40 overflow-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-amber-200 dark:border-amber-800">
                    <th class="py-1.5 pr-2 font-medium">Measurement</th>
                    <th class="py-1.5 pr-2 font-medium">Destination</th>
                    <th class="py-1.5 pr-2 font-medium text-right">Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-amber-100 dark:border-amber-900/40">
                        <td class="py-1.5 pr-2">{{ $row['measurement'] }}</td>
                        <td class="py-1.5 pr-2">{{ $row['destination'] }}</td>
                        <td class="py-1.5 text-right font-medium">RM {{ number_format($row['price'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-3 text-gray-500">No special prices on file.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
