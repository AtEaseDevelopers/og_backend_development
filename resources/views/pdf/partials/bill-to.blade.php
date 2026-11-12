{{-- the standard billing block on every document (client, 5–7 Nov 2026): a BILLING INFORMATION heading over a rule, then
     Bill to (the code, then the name on the second line), Reg. No., Address (always three lines), Tel / PIC on one
     line, Email (client, 13 Nov 2026) — bold uppercase labels, values in normal text.
     Pass 'heading' => false for no heading, or another text; 'addressWidth' => the characters a line of the address
     takes (44; fewer in a half-page column). --}}
@php $btHeading = $heading ?? 'Billing Information'; @endphp
<table class="bt">
    @if ($btHeading !== false)<tr><th colspan="2">{{ $btHeading }}</th></tr>@endif
    <tr><td class="bt-k">{{ $label ?? 'Bill to' }}</td><td class="bt-v">@if (filled($code ?? null)){{ $code }}<br>@endif{{ filled($name ?? null) ? $name : '—' }}</td></tr>
    <tr><td class="bt-k">Reg. No.</td><td class="bt-v">{{ filled($reg_no ?? null) ? $reg_no : '—' }}</td></tr>
    <tr><td class="bt-k">Address</td><td class="bt-v">{!! \App\Support\AddressLines::threeHtml(filled($address ?? null) && $address !== '—' ? $address : null, $addressWidth ?? 44) !!}</td></tr>
    <tr><td class="bt-k">Tel / PIC</td><td class="bt-v">{{ \App\Support\AddressLines::telPic($phone ?? null, $pic ?? null) }}</td></tr>
    <tr><td class="bt-k">Email</td><td class="bt-v">{{ filled($email ?? null) ? $email : '—' }}</td></tr>
</table>
