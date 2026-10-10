@php
    $d = $this->detail();
    $order = $d['order'];
    $enquiry = $d['enquiry'];
    $can = $d['can'];
    $ov = $d['overview'];
    $pr = $d['pricing'];
    $banner = $d['banner'];
    $hasOwner = (bool) ($order?->salesperson_id ?? $enquiry?->salesperson_id);
    $needsHeartbeat = $enquiry && ! $d['lock']['locked_by_other'] && in_array($d['stage']['key'], ['enquiry', 'pending_salesperson', 'quotation'], true);
    // payment summary / linked records (documents) / customer confirmation are cards of the overview: keep Overview highlighted
    $activeTab = $this->tab === 'overview' ? ($this->focus === 'pricing' ? 'pricing' : 'overview') : $this->tab;
    $hasDocuments = $order || ($ov['mode'] === 'enquiry' && $ov['form']['attachments'] !== []);
@endphp

<x-filament-panels::page class="ow-page">
    <div x-data
         x-init="@if ($this->focus) $nextTick(() => setTimeout(() => document.getElementById('og-section-{{ $this->focus }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150)) @endif"
         x-on:og-scroll.window="setTimeout(() => document.getElementById($event.detail.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120)">

        @if ($needsHeartbeat)
            <div wire:poll.2s="heartbeat" class="hidden" aria-hidden="true"></div>
        @endif

        {{-- Header --}}
        <a href="{{ $d['urls']['index'] }}" class="ow-back">← Back to orders</a>
        <div class="ow-head" style="margin-bottom:0">
            <div>
                <div class="ow-crumb">Order details · {{ $d['customer'] }}</div>
                <div class="ow-title-mono">{{ $d['number'] }}</div>
                <div class="ow-chips">
                    <span class="ow-pill ow-pill-{{ $d['stage']['color'] }}">{{ $d['stage']['label'] }}</span>
                    <span class="ow-pill ow-pill-{{ $d['payment']['color'] }}">{{ $d['payment']['label'] }}</span>
                    @if ($d['order_type'])
                        <span class="ow-pill ow-pill-dark">{{ $d['order_type'] }}</span>
                    @endif
                    @if ($d['document_number'])
                        <span class="ow-pill ow-pill-gray ow-pill-plain ow-mono">{{ $d['document_number'] }} · v{{ $d['version'] }}</span>
                    @endif
                </div>
            </div>
            <div class="ow-actions">
                @if ($hasDocuments)
                    <button type="button" wire:click="setTab('documents')" class="ow-btn">View documents</button>
                @endif
                @if ($d['urls']['full_editor'])
                    <a href="{{ $d['urls']['full_editor'] }}" class="ow-btn ow-btn-primary">Edit order details</a>
                @endif
            </div>
        </div>

        {{-- Progress --}}
        <div class="ow-steps">
            @foreach ($d['steps'] as $i => $step)
                @if ($i > 0)<span class="ow-step-sep">›</span>@endif
                <span @class(['ow-step', 'ow-step-'.$step['state']])>
                    <span class="ow-step-n">{{ $step['state'] === 'done' ? '✓' : $step['n'] }}</span>
                    {{ $step['label'] }}
                </span>
            @endforeach
        </div>

        {{-- Action banner --}}
        @if ($banner)
            <div class="ow-banner ow-banner-{{ $banner['tone'] }}">
                <div>
                    <div class="ow-banner-title">{{ $banner['title'] }}</div>
                    <div class="ow-banner-text">{{ $banner['text'] }}</div>
                </div>
                @if ($banner['cta_label'])
                    @switch($banner['cta_kind'])
                        @case('url')
                            <a href="{{ $banner['cta_value'] }}" class="ow-btn">{{ $banner['cta_label'] }}</a>
                            @break
                        @case('action')
                            <button type="button" wire:click="mountAction('{{ $banner['cta_value'] }}')" class="ow-btn">{{ $banner['cta_label'] }}</button>
                            @break
                        @case('tab')
                            <button type="button" wire:click="setTab('{{ $banner['cta_value'] }}')" class="ow-btn">{{ $banner['cta_label'] }}</button>
                            @break
                        @case('scroll')
                            <button type="button" x-on:click="document.getElementById('{{ $banner['cta_value'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' })" class="ow-btn">{{ $banner['cta_label'] }}</button>
                            @break
                        @default
                            <button type="button" wire:click="{{ $banner['cta_value'] }}" class="ow-btn">{{ $banner['cta_label'] }}</button>
                    @endswitch
                @endif
            </div>
        @endif

        @if ($d['lock']['locked_by_other'])
            <div class="ow-callout ow-callout-warning" style="margin-top:.75rem">
                Currently being attended by <strong>{{ $d['lock']['locked_by'] }}</strong>. This order is read-only until they finish.
            </div>
        @endif

        {{-- Records under the same order number --}}
        @if (count($d['siblings']) > 1)
            <div class="ow-siblings">
                <span>{{ count($d['siblings']) }} consignor &amp; consignee records under {{ $d['number'] }}:</span>
                @foreach ($d['siblings'] as $sib)
                    <a href="{{ $sib['url'] }}" @class(['ow-sibling', 'ow-sibling-current' => $sib['current']])>
                        <span class="ow-mono">{{ $sib['number'] }}</span>
                        <span>→ {{ $sib['consignee'] }}</span>
                        <span class="ow-pill ow-pill-{{ $sib['color'] }}">{{ $sib['status'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Tabs --}}
        <div class="ow-tabs">
            <button type="button" wire:click="setTab('overview')" @class(['ow-tab', 'ow-tab-active' => $activeTab === 'overview'])>Overview</button>
            <button type="button" wire:click="setTab('pricing')" @class(['ow-tab', 'ow-tab-active' => $activeTab === 'pricing'])>Items &amp; pricing</button>
            <button type="button" wire:click="setTab('activity')" @class(['ow-tab', 'ow-tab-active' => $activeTab === 'activity'])>Activity</button>
        </div>

        @if ($this->tab === 'overview')
            <div class="ow-stack">
                {{-- ============================== OVERVIEW ============================== --}}
                @if ($ov['mode'] === 'enquiry')
                    @php $f = $ov['form']; @endphp
                    <div class="ow-grid-overview">
                        <div class="ow-stack">
                        <div class="ow-card ow-card-pad">
                            <div class="ow-card-title">
                                Submitted order form
                                <span class="ow-pill ow-pill-progress">{{ $f['source_label'] }}</span>
                            </div>
                            <div class="ow-dl">
                                <div><div class="ow-dt">Submitted by</div><div class="ow-dd">{{ $f['submitted_by'] }}</div></div>
                                <div><div class="ow-dt">Submitted at</div><div class="ow-dd">{{ $f['submitted_at'] }}</div></div>
                                <div><div class="ow-dt">Customer</div><div class="ow-dd">{{ $f['customer'] }}</div></div>
                                <div><div class="ow-dt">Salesperson</div><div class="ow-dd">{!! $f['salesperson'] ? e($f['salesperson']) : '<span class="ow-pill ow-pill-action">Pending salesperson</span>' !!}</div></div>
                                <div><div class="ow-dt">Enquiry</div><div class="ow-dd">{{ $f['enquiry_ref'] }}</div></div>
                                <div><div class="ow-dt">Received through</div><div class="ow-dd">{{ $f['received_through'] }}</div></div>
                                <div><div class="ow-dt">DO number</div><div class="ow-dd">{{ $f['do_number'] }}</div></div>
                                <div><div class="ow-dt">Requested delivery</div><div class="ow-dd">{{ $f['requested_delivery'] }}</div></div>
                                <div><div class="ow-dt">Order type</div><div class="ow-dd">{{ $f['order_type'] }}@if ($f['service_type']) · {{ $f['service_type'] }}@endif</div></div>
                            </div>
                            <div class="ow-stop">
                                <span class="ow-stop-badge">A</span>
                                <div><div class="ow-stop-title">{{ $f['pickup']['title'] }}</div><div class="ow-stop-sub">{{ $f['pickup']['sub'] }}</div></div>
                            </div>
                            @foreach ($f['destinations'] as $i => $dest)
                                <div class="ow-stop">
                                    <span class="ow-stop-badge">{{ chr(66 + $i) }}</span>
                                    <div><div class="ow-stop-title">{{ $dest['title'] }}</div><div class="ow-stop-sub">{{ $dest['sub'] }}</div></div>
                                </div>
                            @endforeach
                            <div style="margin-top:.85rem;border-top:1px solid var(--ow-line);padding-top:.6rem">
                                @foreach ($f['items'] as $item)
                                    <div class="ow-kv"><span>{{ $item['name'] }}</span><strong>{{ $item['qty'] }}</strong></div>
                                @endforeach
                            </div>
                            @if ($f['instructions'])
                                <p style="margin-top:.6rem">{{ $f['instructions'] }}</p>
                            @endif
                            @if ($f['attachments'] !== [])
                                <details style="margin-top:.6rem" id="og-section-documents" class="ow-anchor" @if ($this->focus === 'documents') open @endif>
                                    <summary class="ow-link" style="cursor:pointer">Attachments ({{ count($f['attachments']) }})</summary>
                                    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem">
                                        @foreach ($f['attachments'] as $file)
                                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="ow-link">
                                                @if ($file['is_image'])<img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" style="height:64px;width:64px;object-fit:cover;border-radius:.4rem;border:1px solid var(--ow-line)">@else {{ $file['name'] }} @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>

                            {{-- Items & pricing sits under the order form (left column) --}}
                            <div>
                                <h2 class="ow-section-title" id="og-section-pricing">Items &amp; pricing</h2>
                                @include('filament.pages.partials.order-pricing', ['d' => $d, 'pr' => $pr, 'can' => $can, 'order' => $order, 'hasOwner' => $hasOwner])
                            </div>
                        </div>

                        <div class="ow-stack">
                            {{-- right column: ownership first (assign the salesperson), then the admin action --}}
                            <div class="ow-card ow-card-pad" id="og-assign-salesperson" style="scroll-margin-top:6rem">
                                <div class="ow-card-title">
                                    Ownership &amp; source
                                    @if ($can['assign_salesperson'])
                                        @include('filament.pages.partials.order-card-edit-buttons', ['editing' => $this->editingOwnership, 'edit' => 'editOwnership', 'cancel' => 'cancelOwnership', 'save' => 'saveOwnership'])
                                    @endif
                                </div>
                                <div class="ow-dl">
                                    <div><div class="ow-dt">Salesperson</div><div class="ow-dd">@include('filament.pages.partials.order-assign-salesperson', ['can' => $can, 'current' => $f['salesperson']])</div></div>
                                    <div><div class="ow-dt">Form origin</div><div class="ow-dd">{{ $ov['ownership']['origin'] }}</div></div>
                                    <div><div class="ow-dt">Entered by</div><div class="ow-dd">{{ $ov['ownership']['entered_by'] }}</div></div>
                                    <div><div class="ow-dt">Customer acceptance</div><div class="ow-dd">{{ $ov['ownership']['acceptance'] }}</div></div>
                                </div>
                            </div>

                            <div class="ow-card ow-card-pad" id="og-admin-action" style="scroll-margin-top:6rem">
                                <div class="ow-card-title">Admin action</div>
                                <span class="ow-pill ow-pill-{{ $ov['admin_action']['status_color'] }}">{{ $ov['admin_action']['status_label'] }}</span>
                                <p style="margin:.6rem 0">{{ $ov['admin_action']['text'] }}</p>
                                <div class="ow-kv"><span>Quoted total</span><strong>{{ $ov['admin_action']['quoted_total'] }}</strong></div>
                                <div class="ow-kv"><span>Customer confirmation</span><span>{{ $ov['admin_action']['confirmation'] }}</span></div>

                                @if ($showRejectForm)
                                    <div style="margin-top:.75rem">
                                        <label class="ow-label">Reason for rejecting this order <span class="ow-req">*</span></label>
                                        <textarea wire:model="rejectReason" rows="3" class="ow-textarea" placeholder="Explain why the order cannot be accepted"></textarea>
                                        @error('rejectReason')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        <div class="ow-actions" style="margin-top:.5rem">
                                            <button type="button" wire:click="toggleRejectForm" class="ow-btn">Cancel</button>
                                            <button type="button" wire:click="rejectEnquiry" class="ow-btn ow-btn-danger">Confirm reject</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="ow-actions-split" style="margin-top:.75rem">
                                        @if ($can['reject_enquiry'])
                                            <button type="button" wire:click="toggleRejectForm" class="ow-btn ow-btn-danger">Reject order</button>
                                        @else
                                            <span></span>
                                        @endif
                                        @if (! empty($d['pricing']['records_url']))
                                            <a href="{{ $d['pricing']['records_url'] }}" class="ow-btn ow-btn-primary">Open order →</a>
                                        @elseif ($can['start_pricing'])
                                            <button type="button" wire:click="startPricing" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">Provide pricing →</button>
                                        @elseif (! $hasOwner && $d['stage']['key'] !== 'closed')
                                            <button type="button" class="ow-btn ow-btn-primary" disabled title="Assign a salesperson first">Provide pricing →</button>
                                        @endif
                                    </div>
                                    @if (! $hasOwner && $d['stage']['key'] !== 'closed')
                                        <div class="ow-note" style="margin-top:.4rem">Assign a salesperson under Ownership &amp; source to see prices and continue.</div>
                                    @endif
                                @endif
                                <div class="ow-note" style="margin-top:.75rem">{{ $ov['admin_action']['note'] }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    @php $co = $ov['customer_order']; @endphp
                    <div class="ow-grid-overview">
                        <div class="ow-stack">
                            @php $detailsEditable = $order && ($can['edit_details'] || $can['change_type']); @endphp
                            <div class="ow-card ow-card-pad">
                                <div class="ow-card-title">
                                    Customer &amp; order
                                    @if ($detailsEditable)
                                        @include('filament.pages.partials.order-card-edit-buttons', ['editing' => $this->editingDetails, 'edit' => 'editDetails', 'cancel' => 'cancelDetails', 'save' => 'saveDetails'])
                                    @endif
                                </div>
                                <div class="ow-dl">
                                    <div><div class="ow-dt">Customer</div><div class="ow-dd">{{ $co['customer'] }}</div></div>
                                    @if ($co['customer_pic'] ?? null)
                                        <div><div class="ow-dt">Customer PIC</div><div class="ow-dd">{{ $co['customer_pic'] }}</div></div>
                                    @endif
                                    <div><div class="ow-dt">Salesperson / SA location</div><div class="ow-dd">{{ $co['salesperson_sa'] }}</div></div>
                                    <div><div class="ow-dt">Enquiry</div><div class="ow-dd">@if ($co['enquiry_url'])<a href="{{ $co['enquiry_url'] }}" class="ow-link">{{ $co['enquiry_ref'] }}</a>@else {{ $co['enquiry_ref'] }} @endif</div></div>
                                    <div><div class="ow-dt">Received through</div><div class="ow-dd">{{ $co['received_through'] }}</div></div>
                                    <div>
                                        <div class="ow-dt">DO number</div>
                                        @if ($this->editingDetails && $can['edit_details'])
                                            <input type="text" wire:model="details.do_number" class="ow-input" maxlength="100" aria-label="DO number">
                                            @error('details.do_number')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        @else
                                            <div class="ow-dd">{{ $co['do_number'] }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="ow-dt">Payment term</div>
                                        @if ($this->editingDetails && $can['change_type'])
                                            <select wire:model="details.order_type" class="ow-select" aria-label="Payment term">
                                                @foreach ($this->paymentTermOptions() as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('details.order_type')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        @else
                                            <div class="ow-dd">{{ $co['order_type'] }}</div>
                                        @endif
                                    </div>
                                    <div><div class="ow-dt">Pricing consent</div><div class="ow-dd">{{ $co['consent'] }}</div></div>
                                    @if ($this->editingDetails && $can['edit_details'])
                                        <div>
                                            <div class="ow-dt">Expected delivery</div>
                                            <input type="text" wire:model="details.expected_delivery" class="ow-input" maxlength="255" placeholder="Optional · e.g. 13/10 before noon" aria-label="Expected delivery date">
                                            @error('details.expected_delivery')<div class="ow-field-error">{{ $message }}</div>@enderror
                                        </div>
                                    @elseif ($co['expected_delivery'])
                                        <div><div class="ow-dt">Expected delivery</div><div class="ow-dd">{{ $co['expected_delivery'] }}</div></div>
                                    @endif
                                    @if ($co['consignor_mode'] ?? null)
                                        <div><div class="ow-dt">Consignor</div><div class="ow-dd">{{ $co['consignor_mode'] }}</div></div>
                                    @endif
                                    @if ($co['consignor_pic'] ?? null)
                                        <div><div class="ow-dt">Consignor PIC</div><div class="ow-dd">{{ $co['consignor_pic'] }}</div></div>
                                    @endif
                                    @if ($co['consignee_pic'] ?? null)
                                        <div><div class="ow-dt">Consignee PIC</div><div class="ow-dd">{{ $co['consignee_pic'] }}</div></div>
                                    @endif
                                </div>
                                <div class="ow-stop">
                                    <span class="ow-stop-badge">A</span>
                                    <div><div class="ow-stop-title">{{ $co['pickup']['title'] }}</div><div class="ow-stop-sub">{{ $co['pickup']['sub'] }}</div></div>
                                </div>
                                @foreach ($co['dropoffs'] as $i => $drop)
                                    <div class="ow-stop">
                                        <span class="ow-stop-badge">{{ chr(66 + $i) }}</span>
                                        <div><div class="ow-stop-title">{{ $drop['title'] }}</div><div class="ow-stop-sub">{{ $drop['sub'] }}</div></div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Items & pricing sits under Customer & order (left column) --}}
                            <div>
                                <h2 class="ow-section-title" id="og-section-pricing">Items &amp; pricing</h2>
                                @include('filament.pages.partials.order-pricing', ['d' => $d, 'pr' => $pr, 'can' => $can, 'order' => $order, 'hasOwner' => $hasOwner])
                            </div>
                        </div>

                        {{-- right column: Record ownership → Payment summary → Linked records --}}
                        <div class="ow-stack">
                            <div class="ow-card ow-card-pad" id="og-assign-salesperson" style="scroll-margin-top:6rem">
                                <div class="ow-card-title">
                                    Record ownership
                                    @if ($can['assign_salesperson'])
                                        @include('filament.pages.partials.order-card-edit-buttons', ['editing' => $this->editingOwnership, 'edit' => 'editOwnership', 'cancel' => 'cancelOwnership', 'save' => 'saveOwnership'])
                                    @endif
                                </div>
                                <div class="ow-dl">
                                    <div><div class="ow-dt">Handled by</div><div class="ow-dd">@include('filament.pages.partials.order-assign-salesperson', ['can' => $can, 'current' => $hasOwner ? $ov['ownership']['handled_by'] : null])</div></div>
                                    <div><div class="ow-dt">Created</div><div class="ow-dd">{{ $ov['ownership']['created'] }}</div></div>
                                    <div><div class="ow-dt">Last activity</div><div class="ow-dd">{{ $ov['ownership']['last_activity'] }}</div></div>
                                </div>
                                {{-- only an order blocked before Block COD was removed: it can still be unblocked --}}
                                @if ($can['unblock_cod'])
                                    <div class="ow-actions" style="margin-top:.75rem;justify-content:flex-start">
                                        <button type="button" wire:click="mountAction('unblockCod')" class="ow-btn ow-btn-sm">Unblock COD</button>
                                    </div>
                                @endif
                            </div>

                            @include('filament.pages.partials.order-payment', ['d' => $d, 'can' => $can, 'order' => $order])

                            @include('filament.pages.partials.order-linked-records', ['ov' => $ov, 'can' => $can, 'order' => $order])
                        </div>
                    </div>
                @endif

                {{-- ============================== CUSTOMER CONFIRMATION ============================== --}}
                @if ($d['show_decision'])
                    <h2 class="ow-section-title" id="og-section-decision">Customer confirmation</h2>
                    <div class="ow-card ow-card-pad">
                        <div class="ow-card-title">
                            Waiting for the customer to accept or reject
                            <span class="ow-pill ow-pill-customer">Pending customer decision</span>
                        </div>
                        <p>
                            Quotation <strong class="ow-mono">{{ $order->number }}</strong> (version {{ $order->version }}) for
                            <strong>RM {{ number_format((float) $order->total_amount, 2) }}</strong>
                            was sent {{ $order->sent_at?->format('d M Y · H:i') ?? '' }}.
                            Record the customer's decision. A rejection needs a reason, is kept in the activity log and returns the order to editing.
                        </p>
                        <div class="ow-actions-split" style="margin-top:.85rem">
                            <div class="ow-actions" style="justify-content:flex-start">
                                @if ($can['revise'])<button type="button" wire:click="mountAction('revise')" class="ow-btn ow-btn-sm">Revise (new version)</button>@endif
                                @if ($can['send'])<button type="button" wire:click="mountAction('send')" class="ow-btn ow-btn-sm">Resend quotation</button>@endif
                            </div>
                            <div class="ow-actions">
                                @if ($can['reject'])<button type="button" wire:click="mountAction('reject')" class="ow-btn ow-btn-danger">Customer rejected</button>@endif
                                @if ($can['accept'])<button type="button" wire:click="mountAction('accept')" class="ow-btn ow-btn-success">Customer accepted</button>@endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($d['stage']['key'] === 'confirmation' && $can['credit_decision'])
                    <div class="ow-card ow-card-pad">
                        <div class="ow-card-title">Credit approval <span class="ow-pill ow-pill-action">Branch manager</span></div>
                        <p>{{ $d['stage']['hint'] }}</p>
                        <div class="ow-actions" style="margin-top:.75rem">
                            <button type="button" wire:click="mountAction('creditDecision')" class="ow-btn ow-btn-primary">Review credit approval →</button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- ============================== ACTIVITY ============================== --}}
            @php $act = $d['activity']; @endphp
            <div class="ow-stack">
                @if ($order)
                    @include('filament.pages.partials.order-offers', ['offers' => app(\App\Support\OrderOfferHistory::class)->for($order)])
                @endif

                @php
                    $from = $this->activityFrom;
                    $to = $this->activityTo;
                    $activityItems = collect($act['items'])
                        ->filter(fn ($i) => ($from === '' || $i['day'] >= $from) && ($to === '' || $i['day'] <= $to))
                        ->values()
                        ->all();
                @endphp
                <div class="ow-card ow-card-pad">
                    <div class="ow-card-title">
                        Activity &amp; notifications
                        <span class="ow-note" style="font-weight:400">{{ count($activityItems) }} of {{ count($act['items']) }} · newest first</span>
                    </div>
                    <div class="ow-activity-filter">
                        <div class="ow-field" style="width:100%;max-width:17rem">
                            <label>Activity date</label>
                            <x-og.date-range from="activityFrom" to="activityTo" :from-value="$from" :to-value="$to" label="Activity date" style="min-width:0" />
                        </div>
                        @if ($from !== '' || $to !== '')
                            <button type="button" wire:click="resetActivityFilter" class="ow-btn-link" style="margin-bottom:.5rem">Reset</button>
                        @endif
                    </div>
                    @if ($activityItems === [])
                        <p class="ow-note">{{ $act['items'] === [] ? 'No activity recorded yet.' : 'No activity in the selected dates.' }}</p>
                    @else
                        <ul class="ow-timeline">
                            @foreach ($activityItems as $item)
                                <li @class(['ow-tl-'.$item['kind']]) title="{{ $item['by'] }}">
                                    <div class="ow-tl-title">{{ $item['title'] }}</div>
                                    @if (! empty($item['lines']))
                                        <div class="ow-tl-offer">
                                            <table>
                                                <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
                                                <tbody>
                                                    @foreach ($item['lines'] as $line)
                                                        <tr>
                                                            <td>{{ $line['item'] }}@if ($line['destination'])<span class="ow-tl-dest"> · {{ $line['destination'] }}</span>@endif</td>
                                                            <td class="num">{{ $line['qty'] }}</td>
                                                            <td class="num">{{ $line['unit'] }}</td>
                                                            <td class="num">{{ $line['amount'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                @if ($item['total'])
                                                    <tfoot><tr><td colspan="3">Price offered</td><td class="num">{{ $item['total'] }}</td></tr></tfoot>
                                                @endif
                                            </table>
                                        </div>
                                    @elseif (! empty($item['total']))
                                        <div class="ow-tl-sub">Price offered {{ $item['total'] }}</div>
                                    @endif
                                    <div class="ow-tl-sub">{{ $item['date'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
