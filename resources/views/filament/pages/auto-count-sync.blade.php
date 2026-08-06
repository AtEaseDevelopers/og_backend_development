<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Manual AutoCount sync</x-slot>
        <x-slot name="description">
            Mode: <code>{{ config('og.autocount.mode') }}</code>.
            Supports Sales Invoice, AR Receipt, Commission PO/PI. Duplicate syncs are skipped unless retry is forced.
        </x-slot>

        <form class="space-y-4">
            {{ $this->form }}
            <div class="flex gap-3">
                <x-filament::button type="button" wire:click="syncSelected(false)">
                    Sync
                </x-filament::button>
                <x-filament::button type="button" color="warning" wire:click="syncSelected(true)">
                    Force retry
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Sync log</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
