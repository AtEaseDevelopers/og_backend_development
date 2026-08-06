<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Cross-branch lorry assignment</x-slot>
        <x-slot name="description">
            Assign a CSN from any source branch to a lorry registered under any company.
            Source branch remains sticky for billing and commission.
        </x-slot>

        <form wire:submit="assign" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit">
                Assign to lorry / job sheet
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
