<x-filament-panels::page>
    <form wire:submit="process" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Collect & generate receipts
        </x-filament::button>
    </form>
</x-filament-panels::page>
