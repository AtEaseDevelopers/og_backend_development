{{--
    Payment summary card (order overview, right column; replaces the former "Payment & release" card and the
    Payment summary tab): status and payment term, Total / Paid / Outstanding, the payment history with uploaded
    slips / receipts, Add payment and per-entry Edit, payment review, and the Admin release where it still applies.
--}}
@php
    $ps = $d['payment_summary'];
    $release = $ps['release'];
@endphp
<div class="ow-card ow-card-pad ow-anchor" id="og-section-payment"
     x-data="{ view: null }"
     x-on:og-file-view.window="view = $event.detail"
     x-on:og-file-view-close.window="view = null">
    <div class="ow-card-title">
        Payment summary
        @if ($ps['can_add'])
            <button type="button" wire:click="mountAction('addPayment')" class="ow-btn ow-btn-sm">+ Add payment</button>
        @endif
    </div>
    <div class="ow-chips" style="margin:0 0 .35rem">
        <span class="ow-pill ow-pill-{{ $ps['color'] }}">{{ $ps['label'] }}</span>
        <span class="ow-pill ow-pill-dark">{{ $ps['order_type'] }}</span>
    </div>
    <div class="ow-kv"><span>Total</span><strong>{{ $ps['total'] }}</strong></div>
    <div class="ow-kv"><span>Paid</span><strong>{{ $ps['paid'] }}</strong></div>
    <div class="ow-kv"><span>Outstanding</span><strong @class(['ow-due' => $ps['outstanding_value'] > 0.004])>{{ $ps['outstanding'] }}</strong></div>

    <div class="ow-dl" style="margin-top:.6rem">
        <div><div class="ow-dt">Payment method</div><div class="ow-dd">{{ $ps['method'] ?? '—' }}</div></div>
        <div><div class="ow-dt">Review status</div><div class="ow-dd">{{ $ps['review_status'] }}</div></div>
        @if ($ps['proforma'])
            <div><div class="ow-dt">Proforma invoice</div><div class="ow-dd ow-mono">{{ $ps['proforma'] }}</div></div>
        @endif
        @if ($ps['approval_levels'])
            <div><div class="ow-dt">Approval</div><div class="ow-dd">{{ $ps['approval_levels'] }}</div></div>
        @endif
    </div>

    @if ($ps['term_text'])
        <div class="ow-callout {{ $ps['cod_blocked'] ? 'ow-callout-danger' : 'ow-callout-info' }}" style="margin-top:.75rem">{{ $ps['term_text'] }}</div>
    @endif
    @if ($ps['note'])
        <div class="ow-callout ow-callout-warning" style="margin-top:.75rem">{{ $ps['note'] }}</div>
    @endif
    <div class="ow-note" style="margin-top:.6rem">{{ $ps['status_note'] }}</div>
    @if ($ps['cod_pending'] ?? false)
        <p class="ow-note" style="margin-top:.35rem">COD · any payment recorded here reduces what the driver collects on delivery. The COD invoice is issued once the order is fully paid.</p>
    @endif

    {{-- Admin release: still needed for a cash order short of payment (and a credit term order not released on confirmation) --}}
    @if ($release['released'])
        <div class="ow-callout ow-callout-success" style="margin-top:.75rem">
            Admin release by {{ $release['by'] }}@if ($release['at']) on {{ $release['at'] }}@endif.
            @if ($release['reason'])<br>{{ $release['reason'] }}@endif
        </div>
    @elseif ($can['release'] && $release['applies'])
        <div class="ow-release">
            <div style="min-width:0">
                <div class="ow-l1">Admin release</div>
                <div class="ow-note">Issue the billing and create the CSN before the balance is fully paid. Outstanding {{ $ps['outstanding'] }}.</div>
            </div>
            <button type="button" wire:click="mountAction('release')" class="ow-btn ow-btn-sm ow-btn-primary">Admin release →</button>
        </div>
    @elseif ($release['applies'])
        <p class="ow-note" style="margin-top:.6rem">The outstanding balance needs an Admin release (HQ Admin, Branch Manager or Finance) before billing and CSN creation.</p>
    @endif

    {{-- Payment history: collapsed by default, click the heading to expand --}}
    <div x-data="{ historyOpen: false }">
    <button type="button" class="ow-pay-head ow-pay-toggle" x-on:click="historyOpen = ! historyOpen" x-bind:aria-expanded="historyOpen.toString()">
        <span>Payment history</span>
        <span class="ow-note">
            {{ count($ps['entries']) }} {{ \Illuminate\Support\Str::plural('entry', count($ps['entries'])) }}
            @php $toReview = $can['review_payments'] ? collect($ps['entries'])->filter(fn ($e) => $e['can_verify'] || $e['can_approve'])->count() : 0; @endphp
            @if ($toReview > 0)
                <span class="ow-pill ow-pill-action">{{ $toReview }} to review</span>
            @endif
            <span class="ow-pay-toggle-label" x-text="historyOpen ? 'Hide' : 'Show'">Show</span>
            <svg class="ow-pay-chevron" x-bind:class="{ 'is-open': historyOpen }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </span>
    </button>
    <div x-show="historyOpen" x-collapse x-cloak>
    @forelse ($ps['entries'] as $e)
        @php $review = $can['review_payments'] && ($e['can_reject'] || $e['can_verify'] || $e['can_approve']); @endphp
        <div class="ow-pay" wire:key="ow-pay-{{ $e['key'] }}">
            <div class="ow-pay-row">
                <div style="min-width:0">
                    <div class="ow-pay-amount">{{ $e['amount'] }}</div>
                    <div class="ow-note">{{ $e['date'] }} · {{ $e['method'] }}</div>
                </div>
                <span class="ow-pill ow-pill-{{ $e['color'] }}">{{ $e['status'] }}</span>
            </div>
            <div class="ow-pay-meta">
                <span>Ref <span class="ow-mono">{{ $e['reference'] ?: '—' }}</span></span>
                <span>Recorded by {{ $e['recorded_by'] }}</span>
                @if ($e['approved_by'])<span>Approved by {{ $e['approved_by'] }}</span>@endif
                @if ($e['edited_by'] ?? null)<span>Edited by {{ $e['edited_by'] }}</span>@endif
            </div>
            @if ($e['remarks'])
                <div class="ow-pay-remarks">{{ $e['remarks'] }}</div>
            @endif
            @if ($e['documents'])
                <div class="ow-note ow-mono" style="margin-top:.2rem">{{ $e['documents'] }}</div>
            @endif
            @if ($e['attachments'] !== [])
                <div class="ow-pay-files">
                    @foreach ($e['attachments'] as $file)
                        @if ($file['missing'])
                            <span class="ow-pay-file ow-pay-file-missing" title="{{ $file['name'] }}">
                                <span class="ow-pay-file-ext">{{ $file['ext'] }}</span>
                                <span class="ow-pay-file-name">File not found</span>
                            </span>
                        @else
                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="ow-pay-file" title="View {{ $file['name'] }}"
                               x-on:click.prevent="$dispatch('og-file-view', { url: @js($file['url']), name: @js($file['name']), image: @js($file['is_image']) })">
                                @if ($file['is_image'])
                                    <img src="{{ $file['url'] }}" alt="Payment slip {{ $file['name'] }}" loading="lazy">
                                @else
                                    <span class="ow-pay-file-ext">{{ $file['ext'] }}</span>
                                @endif
                                <span class="ow-pay-file-name">{{ $file['is_image'] ? 'View slip' : 'Open '.$file['ext'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            @if ($e['can_edit'] || $review)
                <div class="ow-actions" style="margin-top:.5rem">
                    @if ($e['can_edit'])
                        <button type="button" wire:click="mountAction('editPayment', { kind: '{{ $e['kind'] }}', id: {{ $e['id'] }} })" class="ow-btn ow-btn-sm">Edit</button>
                    @endif
                    @if ($review)
                        @if ($e['can_reject'])<button type="button" wire:click="mountAction('rejectPayment', { id: {{ $e['id'] }} })" class="ow-btn ow-btn-sm ow-btn-danger">Reject</button>@endif
                        @if ($e['can_verify'])<button type="button" wire:click="verifyPayment({{ $e['id'] }})" class="ow-btn ow-btn-sm">Verify</button>@endif
                        @if ($e['can_approve'] && ! $e['can_verify'])<button type="button" wire:click="approvePayment({{ $e['id'] }})" class="ow-btn ow-btn-sm ow-btn-success">Approve</button>@endif
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="ow-note" style="margin-top:.4rem">No payment recorded yet.</p>
    @endforelse
    </div>
    </div>

    {{-- slip / receipt viewer: payment history links and the tiles of the payment upload field open here --}}
    <script>
        (() => {
            if (window.ogSlipViewerReady) return;
            window.ogSlipViewerReady = true;

            const isOpen = () => [...document.querySelectorAll('.ow-file-viewer')].some((el) => el.offsetParent !== null || getComputedStyle(el).display !== 'none');

            // a tile in the "Payment slips / receipts" upload field: show that file (new upload or saved file)
            document.addEventListener('click', (event) => {
                const item = event.target.closest('.ow-slip-upload .filepond--item');
                if (! item || event.target.closest('button, a, .filepond--file-action-button, .filepond--open-icon, .filepond--download-icon')) return;

                let host = item.parentElement;
                while (host && ! (window.Alpine && host.hasAttribute('x-data') && Alpine.$data(host)?.pond)) host = host.parentElement;
                const pond = host ? Alpine.$data(host).pond : null;
                const file = pond?.getFile(item.id.replace('filepond--item-', ''));
                if (! file?.file) return;

                event.preventDefault();
                event.stopPropagation();
                window.dispatchEvent(new CustomEvent('og-file-view', { detail: {
                    url: URL.createObjectURL(file.file),
                    name: file.filename,
                    image: /^image\//.test(file.file.type || file.fileType || ''),
                } }));
            }, true);

            // Esc closes only the viewer, not the payment window behind it
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && isOpen()) {
                    event.stopImmediatePropagation();
                    event.preventDefault();
                    window.dispatchEvent(new CustomEvent('og-file-view-close'));
                }
            }, true);
        })();
    </script>
    <template x-teleport="body">
        <div x-show="view" x-cloak x-transition.opacity class="ow-file-viewer" x-on:click.self="view = null" role="dialog" aria-modal="true" aria-label="Payment slip">
            <div class="ow-file-viewer-box" x-show="view">
                <div class="ow-file-viewer-head">
                    <span class="ow-file-viewer-name" x-text="view?.name"></span>
                    <a x-bind:href="view?.url" target="_blank" rel="noopener" class="ow-file-viewer-link">Open in new tab</a>
                    <button type="button" class="ow-file-viewer-close" x-on:click="view = null" aria-label="Close">&times;</button>
                </div>
                <div class="ow-file-viewer-body">
                    <template x-if="view && view.image"><img x-bind:src="view.url" x-bind:alt="view.name"></template>
                    <template x-if="view && ! view.image"><iframe x-bind:src="view.url" title="Payment slip"></iframe></template>
                </div>
            </div>
        </div>
    </template>
</div>
