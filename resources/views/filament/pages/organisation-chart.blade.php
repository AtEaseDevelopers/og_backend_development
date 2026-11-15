<x-filament-panels::page>
    {{-- Organisation Chart (user, 16 Nov 2026): one row of filters (company, role, user), then a card per company with
         its number of branches, and under each branch its users: name, role, contact number, email. Read-only. --}}
    <style>
        .oc-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: -.75rem; }
        .oc-input, .oc-select { height: 2.25rem; padding: 0 .75rem; font-size: .875rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; background-color: #fff; color: inherit; }
        .oc-input { width: 17rem; }
        .oc-select { min-width: 11rem; padding-right: 2rem; }
        .oc-on { border-color: rgb(var(--primary-500)); font-weight: 600; }
        .oc-clear { font-size: .8rem; color: rgb(100 116 139); text-decoration: underline; }
        .oc-count { margin-left: auto; font-size: .82rem; color: rgb(100 116 139); }
        .oc-company { border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff; overflow: hidden; }
        .oc-company + .oc-company { margin-top: .9rem; }
        .oc-company-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .6rem; padding: .65rem .9rem; border-bottom: 1px solid rgb(229 231 235); background: rgb(248 250 252); }
        .oc-company-name { font-weight: 700; font-size: 1rem; }
        .oc-tag { font-size: .75rem; font-weight: 600; padding: .05rem .5rem; border-radius: 999px; background: rgb(226 232 240); color: rgb(51 65 85); }
        .oc-groups { display: grid; gap: .75rem; padding: .75rem .9rem .9rem; grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr)); }
        .oc-group { border: 1px solid rgb(229 231 235); border-radius: .6rem; overflow: hidden; min-width: 0; }
        .oc-group-head { display: flex; align-items: baseline; gap: .5rem; padding: .45rem .7rem; background: rgb(241 245 249); font-weight: 600; font-size: .88rem; }
        .oc-group-sub { font-weight: 400; font-size: .78rem; color: rgb(100 116 139); }
        .oc-group-n { margin-left: auto; font-weight: 400; font-size: .78rem; color: rgb(100 116 139); white-space: nowrap; }
        .oc-table { width: 100%; border-collapse: collapse; font-size: .85rem; table-layout: fixed; }
        .oc-table td { padding: .4rem .7rem; border-top: 1px solid rgb(229 231 235); vertical-align: top; overflow-wrap: anywhere; }
        .oc-name { font-weight: 600; }
        .oc-id { display: block; font-weight: 400; font-size: .75rem; color: rgb(148 163 184); }
        .oc-role { display: inline-block; padding: 0 .45rem; border-radius: 999px; font-size: .72rem; font-weight: 600; line-height: 1.6; background: rgb(239 246 255); color: rgb(29 78 216); border: 1px solid rgb(191 219 254); white-space: nowrap; }
        .oc-muted { color: rgb(148 163 184); }
        .oc-empty { padding: .6rem .7rem; font-size: .82rem; color: rgb(148 163 184); border-top: 1px solid rgb(229 231 235); }
        .oc-none { padding: 2rem 1rem; text-align: center; color: rgb(100 116 139); border: 1px dashed rgb(203 213 225); border-radius: .75rem; }
        .oc-table a { color: inherit; text-decoration: none; }
        .oc-table a:hover { text-decoration: underline; }
        .dark .oc-input, .dark .oc-select { background-color: rgb(31 41 55 / .6); border-color: rgb(75 85 99); }
        .dark .oc-company, .dark .oc-group { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .dark .oc-company-head, .dark .oc-group-head { background: rgb(31 41 55); border-color: rgb(55 65 81); }
        .dark .oc-table td, .dark .oc-empty { border-color: rgb(55 65 81); }
        .dark .oc-tag { background: rgb(55 65 81); color: rgb(226 232 240); }
        .dark .oc-role { background: rgb(30 58 138 / .35); color: rgb(191 219 254); border-color: rgb(30 64 175); }
    </style>

    @php($chart = $this->chart())

    <div class="oc-bar">
        <input type="search" class="oc-input @if ($this->userFilter !== '') oc-on @endif" wire:model.live.debounce.400ms="userFilter" placeholder="Search user: name, Staff ID, email, phone" aria-label="Search user">
        <select class="oc-select @if ($this->companyFilter !== '') oc-on @endif" wire:model.live="companyFilter" aria-label="Filter by company">
            <option value="">All companies</option>
            @foreach ($this->companyOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
        </select>
        <select class="oc-select @if ($this->roleFilter !== '') oc-on @endif" wire:model.live="roleFilter" aria-label="Filter by role">
            <option value="">All roles</option>
            @foreach ($this->roleOptions() as $name => $label)<option value="{{ $name }}">{{ $label }}</option>@endforeach
        </select>
        @if ($chart['filtered'])<button type="button" class="oc-clear" wire:click="clearFilters">Clear</button>@endif
        <span class="oc-count">{{ count($chart['companies']) }} {{ count($chart['companies']) === 1 ? 'company' : 'companies' }} · {{ $chart['total'] }} {{ $chart['total'] === 1 ? 'user' : 'users' }}</span>
    </div>

    <div>
        @forelse ($chart['companies'] as $company)
            <section class="oc-company" wire:key="oc-company-{{ $company['id'] }}">
                <div class="oc-company-head">
                    <span class="oc-company-name">{{ $company['name'] }}</span>
                    @if ($company['code'])<span class="oc-tag">{{ $company['code'] }}</span>@endif
                    <span class="oc-tag">{{ $company['branch_count'] }} {{ $company['branch_count'] === 1 ? 'branch' : 'branches' }}</span>
                    <span class="oc-tag">{{ $company['staff_count'] }} {{ $company['staff_count'] === 1 ? 'user' : 'users' }}</span>
                </div>
                <div class="oc-groups">
                    @foreach ($company['groups'] as $group)
                        <div class="oc-group">
                            <div class="oc-group-head">
                                <span>{{ $group['name'] }}</span>
                                @if ($group['code'])<span class="oc-group-sub">{{ $group['code'] }}</span>@endif
                                <span class="oc-group-n">{{ count($group['users']) }} {{ count($group['users']) === 1 ? 'user' : 'users' }}</span>
                            </div>
                            @if ($group['users'] === [])
                                <div class="oc-empty">{{ $group['is_branch'] ? 'No user assigned to this branch.' : 'No user at company level.' }}</div>
                            @else
                                <table class="oc-table">
                                    <colgroup><col style="width:30%"><col style="width:22%"><col style="width:19%"><col style="width:29%"></colgroup>
                                    <tbody>
                                        @foreach ($group['users'] as $user)
                                            <tr>
                                                <td class="oc-name">{{ $user['name'] }}@if ($user['login_id'])<span class="oc-id">{{ $user['login_id'] }}</span>@endif</td>
                                                <td><span class="oc-role">{{ $user['role'] }}</span></td>
                                                <td>@if ($user['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $user['phone']) }}">{{ $user['phone'] }}</a>@else<span class="oc-muted">No phone</span>@endif</td>
                                                <td>@if ($user['email'])<a href="mailto:{{ $user['email'] }}">{{ $user['email'] }}</a>@else<span class="oc-muted">No email</span>@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="oc-none">No user matches these filters.</div>
        @endforelse
    </div>
</x-filament-panels::page>
