{{-- Payment summary (cash orders) / payment term note (credit term & COD orders). --}}
@if ($pay['mode'] === 'non_cash')
    <div class="ow-card ow-card-pad">
        <div class="ow-callout {{ $pay['cod_blocked'] ? 'ow-callout-danger' : 'ow-callout-info' }}">{{ $pay['text'] }}</div>
        <div class="ow-dl" style="margin-top:.85rem">
            <div><div class="ow-dt">Invoice</div><div class="ow-dd ow-mono">{{ $pay['invoice'] ?? 'Not issued yet' }}</div></div>
            <div><div class="ow-dt">CSN</div><div class="ow-dd ow-mono">{{ $pay['csn'] ?? 'Not created yet' }}</div></div>
        </div>
        @if ($can['generate_billing'])
            <div class="ow-actions" style="margin-top:.85rem">
                <button type="button" wire:click="mountAction('generateBilling')" class="ow-btn ow-btn-primary">Issue invoice → CSN</button>
            </div>
        @endif
    </div>
@elseif ($pay['mode'] === 'order')
    @php
        $r = $pay['review'];
        $bill = $pay['billing'];
        $outstanding = max(0, (float) $order->total_amount - (float) $order->paid_amount);
        $canRecord = $outstanding > 0.004 && in_array($order->status, [\App\Enums\QuotationStatus::Accepted, \App\Enums\QuotationStatus::PendingApproval, \App\Enums\QuotationStatus::Confirmed], true);
    @endphp
    <div class="ow-grid-main">
        <div class="ow-stack">
            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Payment review</div>
                <span class="ow-pill ow-pill-{{ $r['color'] }}">{{ $r['label'] }}</span>
                <div style="margin-top:.5rem">
                    <div class="ow-kv"><span>Order total</span><strong>{{ $r['total'] }}</strong></div>
                    <div class="ow-kv"><span>Paid to date</span><strong>{{ $r['paid'] }}</strong></div>
                    <div class="ow-kv"><span>Outstanding</span><strong>{{ $r['outstanding'] }}</strong></div>
                </div>
                <div class="ow-dl" style="margin-top:.6rem">
                    <div><div class="ow-dt">Payment method</div><div class="ow-dd">{{ $r['method'] }}</div></div>
                    <div><div class="ow-dt">Payment term</div><div class="ow-dd">{{ $r['order_type'] }}</div></div>
                    <div><div class="ow-dt">Review status</div><div class="ow-dd">{{ $r['review_status'] }}</div></div>
                    <div><div class="ow-dt">Approval levels</div><div class="ow-dd">{{ $r['approval_levels'] }}</div></div>
                    @if ($r['proforma'])
                        <div><div class="ow-dt">Proforma invoice</div><div class="ow-dd ow-mono">{{ $r['proforma'] }}</div></div>
                    @endif
                </div>
                @if ($r['note'])
                    <div class="ow-callout ow-callout-warning" style="margin-top:.85rem">{{ $r['note'] }}</div>
                @endif
            </div>

            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Payment history</div>
                @if ($pay['submissions'] === [])
                    <p class="ow-note">No payment recorded yet.</p>
                @else
                    <div class="ow-table-wrap">
                        <table class="ow-price-table">
                            <thead><tr><th>Date</th><th class="ow-num">Amount</th><th>Method</th><th>Reference</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($pay['submissions'] as $s)
                                    <tr wire:key="ow-sub-{{ $s['id'] }}">
                                        <td>{{ $s['date'] }}</td>
                                        <td class="ow-num">{{ $s['amount'] }}</td>
                                        <td>{{ $s['method'] }}</td>
                                        <td>
                                            <div class="ow-mono">{{ $s['reference'] }}</div>
                                            @if ($s['remarks'])<div class="ow-note">{{ $s['remarks'] }}</div>@endif
                                            @if ($s['receipt_url'])<a href="{{ $s['receipt_url'] }}" target="_blank" rel="noopener" class="ow-link" style="font-size:.75rem">View receipt</a>@endif
                                        </td>
                                        <td><span class="ow-pill ow-pill-{{ $s['color'] }}">{{ $s['status'] }}</span></td>
                                        <td>
                                            @if ($can['review_payments'])
                                                <div class="ow-actions">
                                                    @if ($s['can_reject'])<button type="button" wire:click="mountAction('rejectPayment', { id: {{ $s['id'] }} })" class="ow-btn ow-btn-sm ow-btn-danger">Reject</button>@endif
                                                    @if ($s['can_verify'])<button type="button" wire:click="verifyPayment({{ $s['id'] }})" class="ow-btn ow-btn-sm">Verify</button>@endif
                                                    @if ($s['can_approve'] && ! $s['can_verify'])<button type="button" wire:click="approvePayment({{ $s['id'] }})" class="ow-btn ow-btn-sm ow-btn-success">Approve</button>@endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Invoice &amp; accounting</div>
                <div class="ow-dl ow-dl-3">
                    <div><div class="ow-dt">Billing type</div><div class="ow-dd">{{ $bill['billing_type'] }}</div></div>
                    <div><div class="ow-dt">Invoice / Cash Bill</div><div class="ow-dd ow-mono">{{ $bill['invoice'] }}</div></div>
                    <div><div class="ow-dt">Dispatch release</div><div class="ow-dd">{{ $bill['dispatch_release'] }}</div></div>
                    <div><div class="ow-dt">EOD</div><div class="ow-dd">{{ $bill['eod'] }}</div></div>
                    <div><div class="ow-dt">AutoCount sync</div><div class="ow-dd">{{ $bill['autocount'] }}</div></div>
                    <div><div class="ow-dt">Refund note</div><div class="ow-dd">{{ $bill['refund'] }}</div></div>
                </div>
                <p class="ow-note" style="margin-top:.75rem">{{ $bill['note'] }}</p>
                @if ($can['generate_billing'] || $can['send_invoice'])
                    <div class="ow-actions" style="margin-top:.75rem">
                        @if ($can['send_invoice'])<button type="button" wire:click="mountAction('sendInvoice')" class="ow-btn">Email invoice / cash bill</button>@endif
                        @if ($can['generate_billing'])<button type="button" wire:click="mountAction('generateBilling')" class="ow-btn ow-btn-primary">{{ $order->billingStatus() === \App\Enums\BillingStatus::Generated ? 'Create CSN' : 'Issue invoice → CSN' }}</button>@endif
                    </div>
                @endif
            </div>
        </div>

        <div class="ow-stack">
            @if ($canRecord)
                <div class="ow-card ow-card-pad">
                    <div class="ow-card-title">Record payment</div>
                    <div class="ow-field">
                        <label>Payment type</label>
                        <div class="ow-radio-group">
                            <label><input type="radio" value="full" wire:model.live="paymentForm.type"> Full payment</label>
                            <label><input type="radio" value="partial" wire:model.live="paymentForm.type"> Partial payment</label>
                        </div>
                    </div>
                    <div class="ow-fgrid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:.75rem">
                        <div class="ow-field">
                            <label>Amount (RM) <span class="ow-req">*</span></label>
                            <div class="ow-money"><span>RM</span><input type="number" min="0.01" step="0.01" wire:model="paymentForm.amount" class="ow-input" @readonly(($paymentForm['type'] ?? 'full') === 'full')></div>
                            @error('paymentForm.amount')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>Payment date <span class="ow-req">*</span></label>
                            <input type="date" wire:model="paymentForm.payment_date" class="ow-input">
                            @error('paymentForm.payment_date')<div class="ow-field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="ow-field">
                            <label>Method</label>
                            <select wire:model="paymentForm.method" class="ow-select">
                                @foreach ($this->paymentMethodOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ow-field">
                            <label>Reference no.</label>
                            <input type="text" wire:model="paymentForm.reference" class="ow-input" placeholder="Bank / slip reference">
                        </div>
                    </div>
                    <div class="ow-field" style="margin-top:.75rem">
                        <label>Payment slip / receipt image</label>
                        <input type="file" wire:model="paymentReceipt" accept="image/*,application/pdf" class="ow-input">
                        <div wire:loading wire:target="paymentReceipt" class="ow-note">Uploading…</div>
                        @if ($paymentReceipt && method_exists($paymentReceipt, 'isPreviewable') && $paymentReceipt->isPreviewable())
                            <img src="{{ $paymentReceipt->temporaryUrl() }}" alt="Receipt preview" style="margin-top:.5rem;max-height:120px;border-radius:.4rem;border:1px solid var(--ow-line)">
                        @endif
                        @error('paymentReceipt')<div class="ow-field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="ow-field" style="margin-top:.75rem">
                        <label>Remarks</label>
                        <textarea wire:model="paymentForm.remarks" rows="2" class="ow-textarea" placeholder="e.g. Balance to be collected on delivery"></textarea>
                    </div>
                    <div class="ow-note" style="margin-top:.5rem">
                        {{ ($paymentForm['type'] ?? 'full') === 'full'
                            ? 'A full payment clears the balance. Once approved the Cash Bill is issued and the CSN is created.'
                            : 'A partial payment leaves an outstanding balance. Billing and CSN need an Admin release (below).' }}
                    </div>
                    <div class="ow-actions" style="margin-top:.85rem">
                        <button type="button" wire:click="recordPayment" wire:loading.attr="disabled" class="ow-btn ow-btn-primary">Record payment</button>
                    </div>
                </div>
            @endif

            <div class="ow-card ow-card-pad">
                <div class="ow-card-title">Admin release</div>
                @if ($order->isReleased())
                    <div class="ow-callout ow-callout-success">
                        Released by {{ $order->releaser?->name ?? 'Admin' }} on {{ $order->released_at?->format('d M Y · H:i') }}.
                        @if ($order->release_reason)<br>{{ $order->release_reason }}@endif
                    </div>
                @elseif ($can['release'])
                    <p>Release the order to issue the Cash Bill and create the CSN before the balance is fully paid. Outstanding: <strong>RM {{ number_format($outstanding, 2) }}</strong>.</p>
                    <div class="ow-actions" style="margin-top:.75rem">
                        <button type="button" wire:click="mountAction('release')" class="ow-btn ow-btn-primary">Admin release →</button>
                    </div>
                @elseif ($order->billingStatus() === \App\Enums\BillingStatus::Generated)
                    <p class="ow-note">Not needed · billing already issued.</p>
                @else
                    <p class="ow-note">Available to HQ Admin, Branch Manager or Finance once the order is confirmed.</p>
                @endif
            </div>
        </div>
    </div>
@endif
