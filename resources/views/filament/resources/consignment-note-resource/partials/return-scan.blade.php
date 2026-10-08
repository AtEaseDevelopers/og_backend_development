{{--
    "Scan returned CSN" window on the CSN list: each scanned / typed CSN is marked as returned
    (ListConsignmentNotes::scanReturnedCsn). Three ways in: a handheld scanner or typing into the box
    (Enter), the device camera (HTTPS only), or a photo of the QR code.
--}}
<div class="og-rscan">
    {{-- no <form> here: the action modal is already a form (nested forms are dropped by the browser) --}}
    <div class="og-rscan-row">
        <input
            type="text"
            wire:model="returnScan"
            wire:keydown.enter.prevent="scanReturnedCsn"
            class="og-rscan-input"
            placeholder="Scan the QR code or type the CSN number, then Enter"
            autocomplete="off"
            autofocus
            x-init="$nextTick(() => $el.focus())"
        >
        <button type="button" wire:click="scanReturnedCsn" class="og-rscan-btn og-rscan-btn-primary">Mark returned</button>
    </div>

    <div wire:ignore x-data="{
            running: false,
            busy: false,
            error: '',
            scanner: null,
            last: '',
            lastAt: 0,
            load() {
                if (window.Html5Qrcode) return Promise.resolve();
                return new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = @js(asset('js/og/html5-qrcode.min.js'));
                    s.onload = resolve;
                    s.onerror = () => reject(new Error('The scanner could not be loaded.'));
                    document.head.appendChild(s);
                });
            },
            send(text) {
                const now = Date.now();
                if (text === this.last && now - this.lastAt < 3000) return; // the same code held in front of the camera
                this.last = text; this.lastAt = now;
                $wire.scanReturnedCsn(text);
            },
            async toggle() {
                this.error = '';
                if (this.running) return this.stop();
                if (! window.isSecureContext) { this.error = 'The live camera needs the HTTPS site. Use Scan from photo, a handheld scanner, or type the number.'; return; }
                try {
                    this.busy = true;
                    await this.load();
                    this.scanner = this.scanner || new Html5Qrcode('og-rscan-camera');
                    await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } }, (text) => this.send(text), () => {});
                    this.running = true;
                } catch (e) {
                    this.error = (e && e.message) ? e.message : String(e);
                } finally { this.busy = false; }
            },
            async stop() {
                try { if (this.scanner && this.running) await this.scanner.stop(); } catch (e) {}
                this.running = false;
            },
            async fromPhoto(event) {
                const file = event.target.files[0];
                event.target.value = '';
                if (! file) return;
                this.error = '';
                try {
                    this.busy = true;
                    await this.load();
                    if (this.running) await this.stop();
                    this.scanner = this.scanner || new Html5Qrcode('og-rscan-camera');
                    const text = await this.scanner.scanFile(file, false);
                    this.last = ''; this.send(text);
                } catch (e) {
                    this.error = 'No QR code found in that photo. Try again closer, with the QR code in focus.';
                } finally { this.busy = false; }
            },
        }"
        x-on:modal-closed.window="stop()"
        class="og-rscan-cam-wrap"
    >
        <div class="og-rscan-row">
            <button type="button" class="og-rscan-btn" x-on:click="toggle()" x-bind:disabled="busy" x-text="running ? 'Stop camera' : 'Use camera'"></button>
            <label class="og-rscan-btn" x-bind:class="{ 'is-busy': busy }">
                Scan from photo
                <input type="file" accept="image/*" capture="environment" class="og-rscan-file" x-on:change="fromPhoto($event)">
            </label>
        </div>
        <p class="og-rscan-error" x-show="error" x-text="error"></p>
        <div id="og-rscan-camera" class="og-rscan-camera" x-show="running"></div>
    </div>

    <div class="og-rscan-log">
        <div class="og-rscan-log-head">Scanned this session <span>{{ count($this->returnScanLog) }}</span></div>
        @forelse ($this->returnScanLog as $row)
            <div class="og-rscan-item" wire:key="rscan-{{ $loop->index }}-{{ $row['id'] }}">
                <div style="min-width:0">
                    <div class="og-rscan-number">{{ $row['number'] }}</div>
                    <div class="og-rscan-sub">{{ $row['customer'] ?: '—' }}</div>
                </div>
                <span class="og-rscan-tag og-rscan-tag-{{ $row['tone'] }}">{{ $row['message'] }}</span>
                @if ($row['returned'] && $row['id'])
                    <button type="button" class="og-rscan-undo" wire:click="markCsnNotReturned({{ $row['id'] }})" wire:confirm="Mark {{ $row['number'] }} as not returned?">Not returned</button>
                @endif
            </div>
        @empty
            <p class="og-rscan-empty">Nothing scanned yet.</p>
        @endforelse
    </div>
</div>

<style>
    .og-rscan { display: flex; flex-direction: column; gap: .85rem; }
    .og-rscan-row { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .og-rscan-input { flex: 1; min-width: 12rem; padding: .6rem .75rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; font-size: .95rem; background: #fff; color: inherit; }
    .dark .og-rscan-input { background: rgb(17 24 39); border-color: rgb(55 65 81); }
    .og-rscan-btn { position: relative; display: inline-flex; align-items: center; padding: .55rem .85rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; font-size: .85rem; font-weight: 600; background: #fff; cursor: pointer; color: inherit; }
    .dark .og-rscan-btn { background: rgb(31 41 55); border-color: rgb(55 65 81); }
    .og-rscan-btn-primary { background: rgb(17 24 39); color: #fff; border-color: rgb(17 24 39); }
    .og-rscan-btn.is-busy, .og-rscan-btn:disabled { opacity: .6; cursor: wait; }
    .og-rscan-file { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .og-rscan-error { margin: .4rem 0 0; font-size: .8rem; color: rgb(185 28 28); }
    .og-rscan-camera { margin-top: .5rem; max-width: 22rem; border-radius: .5rem; overflow: hidden; }
    .og-rscan-log { border-top: 1px solid rgb(229 231 235); padding-top: .6rem; max-height: 18rem; overflow-y: auto; }
    .dark .og-rscan-log { border-color: rgb(55 65 81); }
    .og-rscan-log-head { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139); margin-bottom: .35rem; }
    .og-rscan-log-head span { margin-left: .25rem; }
    .og-rscan-item { display: flex; align-items: center; gap: .6rem; padding: .45rem 0; border-bottom: 1px solid rgb(243 244 246); }
    .dark .og-rscan-item { border-color: rgb(31 41 55); }
    .og-rscan-item > div { flex: 1; }
    .og-rscan-number { font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; }
    .og-rscan-sub { font-size: .75rem; color: rgb(100 116 139); }
    .og-rscan-tag { font-size: .72rem; font-weight: 600; padding: .15rem .5rem; border-radius: 999px; white-space: nowrap; }
    .og-rscan-tag-success { background: rgb(220 252 231); color: rgb(21 128 61); }
    .og-rscan-tag-warning { background: rgb(254 243 199); color: rgb(146 64 14); }
    .og-rscan-tag-danger { background: rgb(254 226 226); color: rgb(185 28 28); }
    .og-rscan-tag-gray { background: rgb(243 244 246); color: rgb(75 85 99); }
    .og-rscan-undo { font-size: .75rem; font-weight: 600; color: rgb(185 28 28); background: none; border: 0; cursor: pointer; white-space: nowrap; }
    .og-rscan-empty { font-size: .85rem; color: rgb(100 116 139); margin: .25rem 0; }
</style>
