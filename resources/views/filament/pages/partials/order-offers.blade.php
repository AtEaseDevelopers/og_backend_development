{{-- "Prices offered to the customer": one compact block per offer (sent / accepted / rejected). Expects $offers. --}}
@if (($offers ?? []) !== [])
    <div class="ow-card ow-card-pad">
        <div class="ow-card-title">
            Prices offered to the customer
            <span class="ow-note" style="font-weight:400">{{ count($offers) }} {{ \Illuminate\Support\Str::plural('record', count($offers)) }} · newest first</span>
        </div>

        <div class="ow-offers">
            @foreach ($offers as $i => $offer)
                <div class="ow-offer" wire:key="ow-offer-{{ $i }}">
                    <div class="ow-offer-head">
                        <div class="ow-offer-meta">
                            <span class="ow-pill ow-pill-{{ $offer['color'] }}">{{ $offer['label'] }}</span>
                            @if ($offer['version'])<span class="ow-offer-chip">Version {{ $offer['version'] }}</span>@endif
                            @if ($offer['channel'])<span class="ow-offer-chip">{{ $offer['channel'] }}</span>@endif
                            @if ($offer['destination'])<span class="ow-offer-chip">→ {{ $offer['destination'] }}</span>@endif
                            <span class="ow-note">{{ $offer['at'] }} · {{ $offer['by'] }}</span>
                        </div>
                        @if ($offer['total'] !== null)
                            <div class="ow-offer-total">RM {{ number_format($offer['total'], 2) }}</div>
                        @endif
                    </div>

                    @if ($offer['reason'])
                        <div class="ow-offer-reason">{{ $offer['reason'] }}</div>
                    @endif

                    @if ($offer['lines'] !== [])
                        @php
                            $visible = array_slice($offer['lines'], 0, 3);
                            $hidden = array_slice($offer['lines'], 3);
                        @endphp
                        <table class="ow-offer-lines">
                            <thead>
                                <tr><th>Item</th><th class="ow-num">Qty</th><th class="ow-num">Unit price</th><th class="ow-num">Amount</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($visible as $line)
                                    <tr>
                                        <td>{{ $line['item'] }}@if (! $offer['destination'] && ! empty($line['destination']))<span class="ow-note"> · {{ $line['destination'] }}</span>@endif</td>
                                        <td class="ow-num">{{ $line['qty'] }}</td>
                                        <td class="ow-num">{{ $line['unit'] !== null ? 'RM '.number_format($line['unit'], 2) : '—' }}</td>
                                        <td class="ow-num">{{ $line['amount'] !== null ? 'RM '.number_format($line['amount'], 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($hidden !== [])
                                <tbody x-data="{ open: false }">
                                    @foreach ($hidden as $line)
                                        <tr x-show="open" x-cloak>
                                            <td>{{ $line['item'] }}@if (! $offer['destination'] && ! empty($line['destination']))<span class="ow-note"> · {{ $line['destination'] }}</span>@endif</td>
                                            <td class="ow-num">{{ $line['qty'] }}</td>
                                            <td class="ow-num">{{ $line['unit'] !== null ? 'RM '.number_format($line['unit'], 2) : '—' }}</td>
                                            <td class="ow-num">{{ $line['amount'] !== null ? 'RM '.number_format($line['amount'], 2) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="4" style="border:0;padding-top:.35rem">
                                            <button type="button" class="ow-btn-link" x-on:click="open = ! open"
                                                    x-text="open ? 'Show fewer items' : 'Show all {{ count($offer['lines']) }} items'"></button>
                                        </td>
                                    </tr>
                                </tbody>
                            @endif
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
