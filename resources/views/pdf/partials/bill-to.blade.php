{{-- the standard billing block on every document (client, 5–7 Nov 2026): a BILLING INFORMATION heading over a rule, then
     Bill to (code + name), Reg. No., Address, PIC, Tel, Email — bold uppercase labels, values in normal text.
     Pass 'heading' => false for no heading, or another text. --}}
@php $btHeading = $heading ?? 'Billing Information'; @endphp
<table class="bt">
    @if ($btHeading !== false)<tr><th colspan="2">{{ $btHeading }}</th></tr>@endif
    <tr><td class="bt-k">{{ $label ?? 'Bill to' }}</td><td class="bt-v">{{ trim(implode(' ', array_filter([$code ?? null, $name ?? null]))) ?: '—' }}</td></tr>
    <tr><td class="bt-k">Reg. No.</td><td class="bt-v">{{ filled($reg_no ?? null) ? $reg_no : '—' }}</td></tr>
    <tr><td class="bt-k">Address</td><td class="bt-v">{!! filled($address ?? null) ? nl2br(e($address)) : '—' !!}</td></tr>
    <tr><td class="bt-k">PIC</td><td class="bt-v">{{ filled($pic ?? null) ? $pic : '—' }}</td></tr>
    <tr><td class="bt-k">Tel</td><td class="bt-v">{{ filled($phone ?? null) ? $phone : '—' }}</td></tr>
    <tr><td class="bt-k">Email</td><td class="bt-v">{{ filled($email ?? null) ? $email : '—' }}</td></tr>
</table>
