{{-- Bottom-right toast (3 s) the moment a customer submits. Live feed is checked every second while this tab is open. --}}
@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $canSee = $tenant && auth()->check() && \App\Filament\Pages\Orders::canAccess();
@endphp
@if ($canSee)
<style>
    .og-new-order-toast { background: #ea580c !important; color: #fff !important; }
    .og-new-order-toast .swal2-title,
    .og-new-order-toast .swal2-html-container { color: #fff !important; }
    .og-new-order-toast .swal2-timer-progress-bar { background: rgba(255, 255, 255, .55) !important; }
</style>
<script src="{{ asset('js/og/sweetalert2.all.min.js') }}?v={{ @filemtime(public_path('js/og/sweetalert2.all.min.js')) }}"></script>
<script>
    (() => {
        if (window.OgNewOrderAlert) {
            return;
        }

        const SwalPopup = window.Swal || window.Sweetalert2;
        const feedUrl = @js(route('filament.admin.new-portal-orders', ['tenant' => $tenant]));
        const ordersUrl = @js(\App\Filament\Pages\Orders::getUrl());
        const seenKey = @js('og-new-order-seen:v4:'.auth()->id().':'.$tenant->getKey());
        const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        window.OgNewOrderAlert = {
            open: false,

            async check() {
                if (this.open || document.hidden || ! SwalPopup) {
                    return;
                }

                let data;
                try {
                    const response = await fetch(feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (! response.ok) {
                        return;
                    }
                    data = await response.json();
                } catch (e) {
                    return;
                }

                this.updateBadge(data.count);

                const seen = parseInt(localStorage.getItem(seenKey) || '0', 10);
                if (! data.count || data.latest_id <= seen) {
                    return;
                }

                const fresh = data.orders.filter((o) => o.id > seen);
                const first = (fresh[0] || data.orders[0] || {});
                const extra = Math.max(0, (fresh.length || data.count) - 1);
                const title = extra > 0
                    ? `${fresh.length || data.count} new customer orders`
                    : 'New customer order';
                const text = extra > 0
                    ? `${escape(first.number)} and ${extra} more`
                    : `${escape(first.number)} · ${escape(first.customer)}`;

                this.open = true;
                localStorage.setItem(seenKey, String(data.latest_id));

                await SwalPopup.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'warning',
                    iconColor: '#fff',
                    background: '#ea580c',
                    color: '#fff',
                    title,
                    html: `<span style="font-size:.85rem">${text}</span>`,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    showCloseButton: false,
                    customClass: { popup: 'og-new-order-toast' },
                });
                this.open = false;
            },

            updateBadge(count) {
                const link = [...document.querySelectorAll('.fi-sidebar-nav a')].find((a) => a.href.split('?')[0] === ordersUrl);
                const badge = link?.querySelector('.fi-badge');
                if (badge) {
                    const label = badge.querySelector('.truncate') || badge;
                    label.textContent = String(count);
                    badge.style.display = count ? '' : 'none';
                }
            },
        };

        const run = () => window.OgNewOrderAlert.check();
        let tick = null;
        const start = () => {
            if (tick || document.hidden) {
                return;
            }
            run();
            tick = setInterval(run, 1000);
        };
        const stop = () => {
            if (tick) {
                clearInterval(tick);
                tick = null;
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
        document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    })();
</script>
@endif
