<x-filament-panels::page>
    <div>
        <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="text-sm font-semibold text-primary-600 dark:text-primary-400">← All reports</a>
    </div>

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
