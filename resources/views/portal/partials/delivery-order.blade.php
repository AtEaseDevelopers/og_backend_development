{{-- one delivery order: status, lorry, proof of delivery or failure details, tracking link + QR --}}
<div class="card">
    <div class="page-head">
        <div>
            <strong>{{ $do['number'] }}</strong> <span class="muted">· {{ $do['kind'] }}</span>
            <div class="muted" style="font-size:.85rem">
                @if ($do['lorry'])Lorry {{ $do['lorry'] }}@endif
                @if ($do['driver']) · Driver {{ $do['driver'] }}@endif
            </div>
        </div>
        {!! \App\Support\PortalBadge::html(['label' => $do['status'], 'tone' => $do['tone']]) !!}
    </div>

    @if ($do['pod'])
        <div style="margin-top:.75rem">
            <h4 style="margin:.25rem 0 .5rem">Proof of delivery</h4>
            <div class="grid-info">
                <div><span class="muted">Received by</span><br>{{ $do['pod']['recipient'] ?: '—' }}</div>
                <div><span class="muted">Delivered at</span><br>{{ $do['pod']['delivered_at'] ?? '—' }}</div>
                @if ($do['pod']['remarks'])<div><span class="muted">Remarks</span><br>{{ $do['pod']['remarks'] }}</div>@endif
            </div>
            @if ($do['pod']['photos'] || $do['pod']['signature'])
                <div class="photos" style="margin-top:.75rem">
                    @foreach ($do['pod']['photos'] as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Delivery photo"></a>
                    @endforeach
                    @if ($do['pod']['signature'])
                        <a href="{{ $do['pod']['signature'] }}" target="_blank" rel="noopener" title="Signature"><img src="{{ $do['pod']['signature'] }}" alt="Signature" style="object-fit:contain"></a>
                    @endif
                </div>
            @endif
            @if ($do['pod']['document'])<p style="margin:.5rem 0 0"><a href="{{ $do['pod']['document'] }}" target="_blank" rel="noopener">View signed POD document</a></p>@endif
        </div>
    @endif

    @if ($do['failure'])
        <div style="margin-top:.75rem;padding:.75rem;border-radius:.5rem;background:#fef2f2;border:1px solid #fecaca">
            <h4 style="margin:0 0 .5rem;color:#991b1b">Delivery not completed</h4>
            <div class="grid-info">
                <div><span class="muted">Reason</span><br>{{ $do['failure']['reason'] }}</div>
                <div><span class="muted">When</span><br>{{ $do['failure']['failed_at'] ?? '—' }}</div>
                @if ($do['failure']['remarks'])<div><span class="muted">Driver remarks</span><br>{{ $do['failure']['remarks'] }}</div>@endif
            </div>
            @if ($do['failure']['photos'])
                <div class="photos" style="margin-top:.75rem">
                    @foreach ($do['failure']['photos'] as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="Failure photo"></a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if (($showTracking ?? false) && $do['tracking_url'])
        <div style="display:flex;gap:1rem;align-items:center;margin-top:.75rem;flex-wrap:wrap">
            @if ($do['qr'])<img src="{{ $do['qr'] }}" alt="Tracking QR code" style="width:96px;height:96px">@endif
            <div style="flex:1;min-width:220px">
                <div class="muted" style="font-size:.85rem">Public tracking link (no login needed) — share it with your consignee or let them scan the QR code.</div>
                <input readonly value="{{ $do['tracking_url'] }}" onclick="this.select()" style="margin:.4rem 0 0">
            </div>
        </div>
    @endif
</div>
