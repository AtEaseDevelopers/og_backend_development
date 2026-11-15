{{-- The History window of the Edit quotation page (user, 16 Nov 2026): every save that changed something, the latest
     first — when, who, and for each field the value before and after. $history: QuoteChangeLog::history(). --}}
<style>
    .qh-empty { padding: 1.5rem 1rem; text-align: center; color: rgb(100 116 139); border: 1px dashed rgb(203 213 225); border-radius: .6rem; font-size: .9rem; }
    .qh-entry + .qh-entry { margin-top: 1rem; }
    .qh-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; margin-bottom: .35rem; font-size: .88rem; }
    .qh-when { font-weight: 700; }
    .qh-who { color: rgb(71 85 105); }
    .qh-n { margin-left: auto; font-size: .78rem; color: rgb(100 116 139); }
    .qh-table { width: 100%; border-collapse: collapse; font-size: .85rem; border: 1px solid rgb(229 231 235); table-layout: fixed; }
    .qh-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139); background: rgb(248 250 252); padding: .35rem .6rem; }
    .qh-table td { padding: .4rem .6rem; border-top: 1px solid rgb(229 231 235); vertical-align: top; overflow-wrap: anywhere; white-space: pre-line; }
    .qh-field { font-weight: 600; }
    .qh-from { color: rgb(153 27 27); background: rgb(254 242 242); }
    .qh-to { color: rgb(22 101 52); background: rgb(240 253 244); }
    .qh-blank { color: rgb(148 163 184); font-style: italic; }
    .dark .qh-table, .dark .qh-table td { border-color: rgb(55 65 81); }
    .dark .qh-table th { background: rgb(31 41 55); color: rgb(148 163 184); }
    .dark .qh-from { background: rgb(127 29 29 / .25); color: rgb(254 202 202); }
    .dark .qh-to { background: rgb(20 83 45 / .3); color: rgb(187 247 208); }
</style>

@if ($history->isEmpty())
    <div class="qh-empty">No change recorded for this quotation yet. Each save that changes something is listed here from now on.</div>
@else
    @foreach ($history as $entry)
        <div class="qh-entry">
            <div class="qh-head">
                <span class="qh-when">{{ $entry['at'] }}</span>
                <span class="qh-who">by {{ $entry['by'] }}</span>
                <span class="qh-n">{{ count($entry['changes']) }} {{ count($entry['changes']) === 1 ? 'field' : 'fields' }} changed</span>
            </div>
            <table class="qh-table">
                <colgroup><col style="width:30%"><col style="width:35%"><col style="width:35%"></colgroup>
                <thead><tr><th>Field</th><th>From</th><th>To</th></tr></thead>
                <tbody>
                    @foreach ($entry['changes'] as $change)
                        <tr>
                            <td class="qh-field">{{ $change['field'] ?? '' }}</td>
                            <td class="qh-from">@if (($change['from'] ?? '') === '')<span class="qh-blank">empty</span>@else{{ $change['from'] }}@endif</td>
                            <td class="qh-to">@if (($change['to'] ?? '') === '')<span class="qh-blank">empty</span>@else{{ $change['to'] }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
