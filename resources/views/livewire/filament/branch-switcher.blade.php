@if ($currentBranch)
    <div class="fi-branch-switcher flex shrink-0 items-center">
        <x-filament::dropdown placement="bottom-start" teleport>
            <x-slot name="trigger">
                <button
                    type="button"
                    @class([
                        'group flex items-center gap-x-2 rounded-lg px-2.5 py-1.5 text-sm font-medium outline-none transition duration-75',
                        'hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5' => $canSwitch,
                    ])
                    @disabled(! $canSwitch)
                    title="Current branch: {{ $currentBranch->code }} — {{ $currentBranch->name }}"
                >
                    <x-filament::icon
                        icon="heroicon-m-building-office-2"
                        class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400"
                    />

                    <span class="whitespace-nowrap text-gray-950 dark:text-white">
                        <span class="font-semibold">{{ $currentBranch->code }}</span>
                        <span class="text-gray-500 dark:text-gray-400"> — {{ $currentBranch->name }}</span>
                    </span>

                    @if ($canSwitch)
                        <x-filament::icon
                            icon="heroicon-m-chevron-down"
                            class="h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400"
                        />
                    @endif
                </button>
            </x-slot>

            @if ($canSwitch)
                <x-filament::dropdown.list>
                    @foreach ($branches as $branch)
                        <x-filament::dropdown.list.item
                            :color="$branch->is($currentBranch) ? 'primary' : 'gray'"
                            icon="heroicon-m-building-office-2"
                            tag="button"
                            type="button"
                            wire:click="switchBranch({{ $branch->id }})"
                            wire:key="branch-switch-{{ $branch->id }}"
                        >
                            <span class="font-medium">{{ $branch->code }}</span>
                            <span class="text-gray-500 dark:text-gray-400"> — {{ $branch->name }}</span>
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endif
        </x-filament::dropdown>
    </div>
@endif
