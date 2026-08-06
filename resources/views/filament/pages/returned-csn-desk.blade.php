<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Receive original signed CSN</x-slot>
        <x-slot name="description">
            Commission payout requires the original signed CSN. Scan QR or select a pending/missing CSN.
            Grace period: {{ config('og.missing_csn_days') }} days after delivery.
        </x-slot>

        <form wire:submit="receive" class="space-y-6">
            {{ $this->form }}
            <div class="flex gap-3">
                <x-filament::button type="submit">
                    Record return
                </x-filament::button>
                <x-filament::button type="button" color="warning" wire:click="flagMissing">
                    Flag overdue as missing
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
