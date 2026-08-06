<x-filament-panels::page.simple>
    <div>
        <div class="grid branch-picker-grid" style="gap: 1.25rem; grid-template-columns: repeat(2, minmax(0, 1fr));">
            <style>
                @media (max-width: 640px) {
                    .branch-picker-grid {
                        grid-template-columns: 1fr !important;
                    }
                }
            </style>
            @forelse ($this->getBranches() as $branch)
                <button
                    type="button"
                    wire:click="enterBranch({{ $branch->id }})"
                    class="group branch-picker-card flex w-full flex-col items-start rounded-xl border border-gray-200 bg-white text-left shadow-sm transition hover:border-primary-500 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400"
                    style="gap: 1.25rem; padding: 1.5rem;"
                >
                    <div class="flex w-full items-center justify-between" style="gap: 1rem;">
                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-500/10 text-sm font-bold text-primary-600 dark:text-primary-400">
                            {{ $branch->code }}
                        </span>
                        <x-filament::icon
                            icon="heroicon-m-arrow-right"
                            class="h-5 w-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-500"
                        />
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                        <div class="text-base font-semibold leading-snug text-gray-950 dark:text-white">
                            {{ $branch->name }}
                        </div>
                        <div class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                            Next: select or register a company
                        </div>
                    </div>
                </button>
            @empty
                <div
                    class="rounded-xl border border-dashed border-gray-300 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400"
                    style="grid-column: 1 / -1; padding: 2rem;"
                >
                    No branches are assigned to your account. Contact HQ admin.
                </div>
            @endforelse
        </div>

        <div class="flex justify-center" style="margin-top: 2.5rem;">
            <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                @csrf
                <x-filament::button type="submit" color="gray" outlined>
                    Sign out
                </x-filament::button>
            </form>
        </div>
    </div>
</x-filament-panels::page.simple>
