{{-- The Role dropdown of the Users list, in the same row as the search box (user, 13 Nov 2026): "All roles" or one
     role; the list follows as soon as it is picked. $roles: role name => label. --}}
<div class="og-inline-filter">
    <select wire:model.live="roleFilter" aria-label="Filter by role" @class(['og-inline-filter-select', 'og-inline-filter-on' => $this->roleFilter !== ''])>
        <option value="">All roles</option>
        @foreach ($roles as $name => $label)
            <option value="{{ $name }}">{{ $label }}</option>
        @endforeach
    </select>
    @if ($this->roleFilter !== '')
        <button type="button" class="og-inline-filter-clear" wire:click="$set('roleFilter', '')" title="Show all roles">Clear</button>
    @endif
</div>

@once
    <style>
        /* right beside the search box: the search box pushes what follows it to the far right, so that push is moved
           behind the dropdown */
        .fi-ta-search-field:has(+ .og-inline-filter) { margin-right: 0 !important; }
        .og-inline-filter { display: inline-flex; align-items: center; gap: .5rem; margin-right: auto; }
        .og-inline-filter-select { height: 2.25rem; min-width: 11rem; padding: 0 2rem 0 .75rem; font-size: .875rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; background-color: #fff; color: inherit; }
        .og-inline-filter-select.og-inline-filter-on { border-color: rgb(var(--primary-500)); font-weight: 600; }
        .og-inline-filter-clear { font-size: .8rem; color: rgb(100 116 139); text-decoration: underline; }
        .dark .og-inline-filter-select { background-color: rgb(31 41 55 / .6); border-color: rgb(75 85 99); }
    </style>
@endonce
