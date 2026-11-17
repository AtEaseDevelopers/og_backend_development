{{-- the lorries of a lorry company (client, 16 Nov 2026): lorry no., type, driver and attendant with their phones --}}
@php
    $lorries = $getRecord()->lorries()->with('defaultDriver')->orderBy('registration_no')->get();
@endphp
@if ($lorries->isEmpty())
    <p class="text-sm text-gray-500">No lorry under this company yet. Pick the company on the lorry in Master Data → Lorry Database.</p>
@else
    <div style="max-height: 22rem; overflow-y: auto">
        <table class="w-full text-sm" style="border-collapse: collapse">
            <thead>
                <tr class="text-left text-gray-500" style="border-bottom: 1px solid rgb(229 231 235)">
                    <th class="py-1 pr-3 font-medium">Lorry no.</th>
                    <th class="py-1 pr-3 font-medium">Type</th>
                    <th class="py-1 pr-3 font-medium">Driver</th>
                    <th class="py-1 pr-3 font-medium">Driver mobile</th>
                    <th class="py-1 pr-3 font-medium">Attendant</th>
                    <th class="py-1 pr-3 font-medium">Attendant mobile</th>
                    <th class="py-1 font-medium">Active</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lorries as $lorry)
                    <tr style="border-bottom: 1px solid rgb(243 244 246)">
                        <td class="py-1 pr-3 font-semibold">{{ $lorry->registration_no }}</td>
                        <td class="py-1 pr-3">{{ $lorry->type ?: '—' }}</td>
                        <td class="py-1 pr-3">{{ $lorry->defaultDriver?->name ?: '—' }}</td>
                        <td class="py-1 pr-3">{{ $lorry->defaultDriver?->phone ?: '—' }}</td>
                        <td class="py-1 pr-3">{{ $lorry->defaultDriver?->attendant_name ?: '—' }}</td>
                        <td class="py-1 pr-3">{{ $lorry->defaultDriver?->attendant_phone ?: '—' }}</td>
                        <td class="py-1">{{ $lorry->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
