{{-- the billing customer block (client, 5 Nov 2026; user, 6 Nov 2026): a bold label and a value in normal text on every
     line — Bill to (code + name), Reg. No., Address, PIC, Tel, Email --}}
<table class="bt">
    <tr><td class="bt-k">{{ $label ?? 'Bill to' }}</td><td class="bt-v">{{ trim(implode(' ', array_filter([$code ?? null, $name ?? null]))) ?: '—' }}</td></tr>
    @if (filled($reg_no ?? null))<tr><td class="bt-k">Reg. No.</td><td class="bt-v">{{ $reg_no }}</td></tr>@endif
    @if (filled($address ?? null))<tr><td class="bt-k">Address</td><td class="bt-v">{!! nl2br(e($address)) !!}</td></tr>@endif
    @if (filled($pic ?? null))<tr><td class="bt-k">PIC</td><td class="bt-v">{{ $pic }}</td></tr>@endif
    @if (filled($phone ?? null))<tr><td class="bt-k">Tel</td><td class="bt-v">{{ $phone }}</td></tr>@endif
    @if (filled($email ?? null))<tr><td class="bt-k">Email</td><td class="bt-v">{{ $email }}</td></tr>@endif
</table>
