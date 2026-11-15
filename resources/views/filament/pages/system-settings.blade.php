<x-filament-panels::page>
    {{-- saved with "Save settings" at the top right; the second button under the form is gone (user, 13 Nov 2026) --}}
    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
