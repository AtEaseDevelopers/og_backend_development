<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $this->moduleTitle }}</x-slot>
        <x-slot name="description">Coming in {{ $this->phase }}</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            This module is registered to match the O&amp;G Transport functional checklist.
            Schema and models are in place; full workflows will be implemented in a later phase.
        </p>
    </x-filament::section>
</x-filament-panels::page>
