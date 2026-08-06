<x-filament-panels::page.simple>
    <div>
        <div class="flex items-center justify-between gap-3">
            <x-filament::button color="gray" outlined wire:click="backToBranches" size="sm">
                ← Change branch
            </x-filament::button>

            <x-filament::button color="primary" wire:click="toggleRegisterForm" size="sm">
                {{ $this->showRegisterForm ? 'Hide registration' : 'Register company' }}
            </x-filament::button>
        </div>

        @if ($this->showRegisterForm)
            <div
                class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 register-company-card"
                style="margin-top: 2rem; padding: 1.75rem;"
            >
                <style>
                    .register-company-card .fi-fo-component-ctn {
                        row-gap: 1.75rem !important;
                        column-gap: 1.5rem !important;
                    }
                    .register-company-card .fi-fo-field-wrp {
                        gap: 0.5rem;
                    }
                </style>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white" style="margin-bottom: 1.5rem;">
                    Register company
                </h2>
                <form wire:submit="registerCompany" style="display: flex; flex-direction: column; gap: 1.75rem;">
                    {{ $this->form }}

                    <div class="flex justify-end" style="padding-top: 0.5rem;">
                        <x-filament::button type="submit">
                            Register & enter system
                        </x-filament::button>
                    </div>
                </form>
            </div>
        @endif

        <div class="grid sm:grid-cols-2" style="margin-top: 2.5rem; gap: 1.25rem;">
            @forelse ($this->getCompanies() as $company)
                <button
                    type="button"
                    wire:click="enterCompany({{ $company->id }})"
                    class="group flex w-full flex-col items-start rounded-xl border border-gray-200 bg-white text-left shadow-sm transition hover:border-primary-500 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400"
                    style="gap: 1.25rem; padding: 1.5rem;"
                >
                    <div class="flex w-full items-start justify-between" style="gap: 1rem;">
                        <div class="flex items-center" style="gap: 0.75rem;">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-500/10 text-sm font-bold text-primary-600 dark:text-primary-400">
                                {{ $company->code }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-md text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400"
                                style="gap: 0.375rem; background: rgba(16, 185, 129, 0.15); padding: 0.25rem 0.625rem;"
                            >
                                <x-filament::icon
                                    icon="heroicon-m-check-badge"
                                    class="h-4 w-4"
                                />
                                Registered
                            </span>
                        </div>
                        <x-filament::icon
                            icon="heroicon-m-arrow-right"
                            class="mt-1 h-5 w-5 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-500"
                        />
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                        <div class="text-base font-semibold leading-snug text-gray-950 dark:text-white">
                            {{ $company->name }}
                        </div>
                        <div class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                            BRN: {{ $company->brn }}
                        </div>
                        <div class="text-xs font-medium text-primary-600 dark:text-primary-400" style="padding-top: 0.25rem;">
                            Click to open this company system →
                        </div>
                    </div>
                </button>
            @empty
                <div
                    class="col-span-full rounded-xl border border-dashed border-gray-300 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400"
                    style="padding: 2rem;"
                >
                    No companies registered in this branch yet. Click <strong>Register company</strong> to create one with BRN.
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
