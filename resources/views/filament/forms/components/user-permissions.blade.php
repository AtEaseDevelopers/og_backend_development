{{-- "Permissions (this user only)" on the user's edit page (user, 13 Nov 2026): one row per module with a tick box
     under View / Create / Edit / Delete, and one row per single action with a tick box under Allow — ticked = this
     user can do it. Each box starts at what the user's role gives (no "from the role" column); a box that differs from
     the role is this user's own exception and is outlined in orange. $roleGrants: permission => what the role gives. --}}
@php
    $path = $getStatePath();
    $abilities = \App\Support\Permissions::ABILITIES;
    $box = function (string $permission, string $label) use ($path, $roleGrants): string {
        $key = str_replace('.', '__', $permission);
        $role = ($roleGrants[$permission] ?? false) ? 'true' : 'false';

        return '<span class="up-box" x-data="{ on: $wire.$entangle(\''.$path.'.'.$key.'\'), role: '.$role.' }" x-bind:class="!! on !== role ? \'up-box-changed\' : \'\'"'
            .' x-bind:title="!! on !== role ? (on ? \'Allowed for this user only (the role cannot)\' : \'Removed for this user only (the role can)\') : \'As the role\'">'
            .'<input type="checkbox" x-model="on" aria-label="'.e($label).'"></span>';
    };
    $groups = [];
    foreach (\App\Support\Permissions::MODULES as $module => $entry) {
        $groups[$entry['group']]['modules'][$module] = $entry;
    }
    foreach (\App\Support\Permissions::ACTIONS as $name => $entry) {
        $groups[$entry['group']]['actions'][$name] = $entry;
    }
@endphp
<div class="up-wrap">
    <table class="up-table">
        <thead>
            <tr>
                <th>Action</th>
                @foreach ($abilities as $abilityLabel)<th class="up-col">{{ $abilityLabel }}</th>@endforeach
                <th class="up-col">Allow</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Support\Permissions::grouped() as $group => $ignored)
                <tr class="up-group"><td colspan="{{ count($abilities) + 2 }}">{{ $group }}</td></tr>
                @foreach ($groups[$group]['modules'] ?? [] as $module => $entry)
                    <tr wire:key="up-m-{{ $module }}">
                        <td>{{ $entry['label'] }} <span class="up-row-note">{{ $entry['note'] }}</span></td>
                        @foreach ($abilities as $ability => $abilityLabel)
                            <td class="up-col">
                                @if (in_array($ability, $entry['abilities'], true))
                                    {!! $box($module.'.'.$ability, $entry['label'].': '.$abilityLabel) !!}
                                @else
                                    <span class="up-none" title="Follows Edit">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="up-col"></td>
                    </tr>
                @endforeach
                @foreach ($groups[$group]['actions'] ?? [] as $name => $entry)
                    <tr wire:key="up-a-{{ str_replace('.', '__', $name) }}">
                        <td>{{ $entry['label'] }}@foreach ($entry['kinds'] as $kind)<span class="up-kind up-kind-{{ strtolower($kind) }}">{{ $kind }}</span>@endforeach</td>
                        @foreach ($abilities as $ability => $abilityLabel)<td class="up-col"></td>@endforeach
                        <td class="up-col">{!! $box($name, $entry['label']) !!}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
<p class="up-foot"><span class="up-box up-box-changed up-box-sample"><input type="checkbox" checked disabled></span> an orange box is different from the user's role (this user only). Put it back and it follows the role again.</p>

@once
    <style>
        .up-wrap { border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff; overflow: auto; max-height: 34rem; }
        .up-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .88rem; }
        .up-table th, .up-table td { padding: .45rem .7rem; border-bottom: 1px solid rgb(229 231 235); text-align: left; }
        .up-table thead th { position: sticky; top: 0; z-index: 2; background: rgb(248 250 252); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(71 85 105); }
        .up-table .up-col { width: 6.5rem; text-align: center; }
        .up-group td { background: rgb(241 245 249); font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(51 65 85); }
        .up-box { display: inline-flex; padding: .2rem; border-radius: .4rem; border: 2px solid transparent; line-height: 0; }
        .up-box input { width: 1.05rem; height: 1.05rem; cursor: pointer; }
        .up-box-changed { border-color: rgb(249 115 22); background: rgb(255 247 237); }
        .up-box-sample { vertical-align: middle; margin-right: .25rem; }
        .up-box-sample input { cursor: default; }
        .up-none { color: rgb(203 213 225); }
        .up-foot { margin-top: .5rem; font-size: .8rem; color: rgb(100 116 139); }
        .up-row-note { margin-left: .4rem; font-size: .78rem; color: rgb(148 163 184); }
        .up-kind { display: inline-block; margin-left: .3rem; padding: 0 .4rem; border-radius: 999px; font-size: .68rem; font-weight: 600; line-height: 1.5; border: 1px solid transparent; vertical-align: middle; }
        .up-kind-view { background: rgb(239 246 255); color: rgb(29 78 216); border-color: rgb(191 219 254); }
        .up-kind-create { background: rgb(240 253 244); color: rgb(21 128 61); border-color: rgb(187 247 208); }
        .up-kind-edit { background: rgb(255 251 235); color: rgb(180 83 9); border-color: rgb(253 230 138); }
        .up-kind-delete { background: rgb(254 242 242); color: rgb(185 28 28); border-color: rgb(254 202 202); }
        .up-kind-approve { background: rgb(250 245 255); color: rgb(126 34 206); border-color: rgb(233 213 255); }
        .up-table tbody tr:not(.up-group):hover td { background: rgb(248 250 252); }
        .dark .up-wrap { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .up-table th, .dark .up-table td { border-color: rgb(55 65 81); }
        .dark .up-table thead th { background: rgb(31 41 55); color: rgb(203 213 225); }
        .dark .up-group td { background: rgb(31 41 55); color: rgb(226 232 240); }
        .dark .up-table tbody tr:not(.up-group):hover td { background: rgb(31 41 55 / .5); }
        .dark .up-box-changed { background: rgb(249 115 22 / .15); }
        .dark .up-none { color: rgb(75 85 99); }
    </style>
@endonce
