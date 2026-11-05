<div class="card">
    <h3>Progress</h3>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
        @foreach ($timeline as $step)
            <div style="flex:1 1 150px;padding:.6rem .75rem;border-radius:.5rem;border:1px solid {{ $step['done'] ? '#bbf7d0' : 'var(--line)' }};background:{{ $step['done'] ? '#f0fdf4' : '#fafaf9' }}">
                <div style="font-weight:600;color:{{ $step['done'] ? '#166534' : '#78716c' }}">{{ $step['done'] ? '✓' : $loop->iteration }} {{ $step['label'] }}</div>
                @if ($step['at'])<div class="muted" style="font-size:.8rem">{{ $step['at'] }}</div>@endif
            </div>
        @endforeach
    </div>
</div>
