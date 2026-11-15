<x-filament-panels::page>
    {{-- Roles & Permission (user, 13 Nov 2026): the actions by module down the side, a tick box per role; saved with
         "Save permissions" at the top right --}}
    <style>
        .rp-note { font-size: .85rem; color: rgb(100 116 139); margin: -.5rem 0 0; }
        /* only the table scrolls, never the page (user, 13 Nov 2026): the table takes the height left under the title and
           the note, and the room the page keeps for the floating Top button is given back */
        .fi-main .fi-page:has(.rp-wrap) { padding-bottom: 0; }
        .rp-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff; overflow: auto; height: calc(100vh - 17rem); min-height: 16rem; }
        .rp-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .88rem; }
        .rp-table th, .rp-table td { padding: .45rem .7rem; border-bottom: 1px solid rgb(229 231 235); }
        .rp-table thead th { position: sticky; top: 0; z-index: 2; background: rgb(248 250 252); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(71 85 105); text-align: center; vertical-align: bottom; }
        .rp-table thead th:first-child { text-align: left; min-width: 22rem; }
        .rp-staff { display: block; font-weight: 400; text-transform: none; letter-spacing: 0; color: rgb(148 163 184); }
        .rp-group td { background: rgb(241 245 249); font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(51 65 85); }
        .rp-cell { text-align: center; }
        .rp-cell input { width: 1.05rem; height: 1.05rem; cursor: pointer; }
        .rp-cell input:disabled { cursor: default; opacity: .55; }
        .rp-table tbody tr:not(.rp-group):hover td { background: rgb(248 250 252); }
        /* the kind of each action, as tags behind its name */
        .rp-kind { display: inline-block; margin-left: .3rem; padding: 0 .4rem; border-radius: 999px; font-size: .68rem; font-weight: 600; line-height: 1.5; border: 1px solid transparent; vertical-align: middle; }
        .rp-kind-view { background: rgb(239 246 255); color: rgb(29 78 216); border-color: rgb(191 219 254); }
        .rp-kind-create { background: rgb(240 253 244); color: rgb(21 128 61); border-color: rgb(187 247 208); }
        .rp-kind-edit { background: rgb(255 251 235); color: rgb(180 83 9); border-color: rgb(253 230 138); }
        .rp-kind-delete { background: rgb(254 242 242); color: rgb(185 28 28); border-color: rgb(254 202 202); }
        .rp-kind-approve { background: rgb(250 245 255); color: rgb(126 34 206); border-color: rgb(233 213 255); }
        .rp-row-note { margin-left: .5rem; font-size: .78rem; color: rgb(148 163 184); }
        .rp-legend { font-size: .8rem; color: rgb(100 116 139); margin: -.25rem 0 0; }
        .rp-all { font-size: .7rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: rgb(var(--primary-600)); text-decoration: underline; cursor: pointer; margin-left: .5rem; }
        .dark .rp-wrap { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .rp-table th, .dark .rp-table td { border-color: rgb(55 65 81); }
        .dark .rp-table thead th { background: rgb(31 41 55); color: rgb(203 213 225); }
        .dark .rp-group td { background: rgb(31 41 55); color: rgb(226 232 240); }
        .dark .rp-table tbody tr:not(.rp-group):hover td { background: rgb(31 41 55 / .5); }
    </style>

    @php
        $roles = $this->roleNames();
        $counts = $this->staffCounts();
        $overrides = $this->overrideCount();
    @endphp

    <p class="rp-note">
        Tick an action to give it to everyone with that role; untick to take it away; then Save permissions. A Super Admin can always do everything.
        @if ($overrides > 0) {{ $overrides }} {{ $overrides === 1 ? 'user has' : 'users have' }} their own exceptions (Users → Permissions). @endif
    </p>

    <p class="rp-legend">
        <span class="rp-kind rp-kind-view" style="margin-left:0">View</span> open and read
        <span class="rp-kind rp-kind-create">Create</span> add new
        <span class="rp-kind rp-kind-edit">Edit</span> change
        <span class="rp-kind rp-kind-delete">Delete</span> remove
        <span class="rp-kind rp-kind-approve">Approve</span> decide (approve / reject / release)
    </p>

    <div class="rp-wrap">
        <table class="rp-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Super Admin<span class="rp-staff">{{ $counts['super_admin'] ?? 0 }} staff</span></th>
                    @foreach ($roles as $role)
                        <th>{{ $this->roleLabel($role) }}<span class="rp-staff">{{ $counts[$role] ?? 0 }} staff</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Support\Permissions::grouped() as $group => $actions)
                    <tr class="rp-group"><td colspan="{{ count($roles) + 2 }}">{{ $group }}</td></tr>
                    @foreach ($actions as $permission => $row)
                        @php $label = $row['label']; @endphp
                        @php $key = \App\Filament\Pages\RolesPermissionsPage::key($permission); @endphp
                        <tr wire:key="rp-{{ $key }}">
                            <td>{{ $label }}@foreach ($row['kinds'] as $kind)<span class="rp-kind rp-kind-{{ strtolower($kind) }}">{{ $kind }}</span>@endforeach @if ($row['note'])<span class="rp-row-note">{{ $row['note'] }}</span>@endif</td>
                            <td class="rp-cell"><input type="checkbox" checked disabled title="A Super Admin can always do everything"></td>
                            @foreach ($roles as $role)
                                <td class="rp-cell"><input type="checkbox" wire:model="matrix.{{ $key }}.{{ $role }}" aria-label="{{ $this->roleLabel($role) }}: {{ $label }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
