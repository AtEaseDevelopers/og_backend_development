<x-filament-panels::page>
    <form wire:submit="reconcile" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Reconcile COD collections
        </x-filament::button>
    </form>
</x-filament-panels::page>
