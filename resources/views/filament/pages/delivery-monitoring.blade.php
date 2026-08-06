<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">4pm incomplete delivery alerts</x-slot>
        <x-slot name="description">
            Scheduled daily at 16:05 via <code>og:flag-incomplete-deliveries</code>.
            Run manually below to refresh alerts for a date.
        </x-slot>

        <form wire:submit="runFlag" class="space-y-4 mb-6">
            {{ $this->form }}
            <x-filament::button type="submit">
                Flag incomplete deliveries
            </x-filament::button>
        </form>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
