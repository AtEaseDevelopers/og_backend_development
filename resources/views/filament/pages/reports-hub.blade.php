<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Operational & finance reports</x-slot>
        <x-slot name="description">Filter by report type / branch / date, then export CSV.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
