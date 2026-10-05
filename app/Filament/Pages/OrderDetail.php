<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\Billing\Actions\ReviewPaymentSubmission;
use App\Domains\Billing\Actions\SendInvoice;
use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Notification\Models\NotificationLog;
use App\Domains\Quotation\Actions\AcceptQuotation;
use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Domains\Quotation\Actions\ChangeOrderType;
use App\Domains\Quotation\Actions\ClosePendingCustomerReviews;
use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\DecideCreditApproval;
use App\Domains\Quotation\Actions\RejectQuotation;
use App\Domains\Quotation\Actions\ReleaseOrder;
use App\Domains\Quotation\Actions\ReviseQuotation;
use App\Domains\Quotation\Actions\SaveOrderPricing;
use App\Domains\Quotation\Actions\SendQuotation;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationRejectionCategory;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Support\OrderDetailData;
use App\Support\QuotationPricingLookup;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Order detail: header with the 7-step progress, the action banner and the stacked
 * Overview / Items & pricing / Payment summary / Documents / Activity sections.
 *
 * Route: orders/{type}/{id} where type is "enquiry" (not priced yet) or "order" (quotation record).
 */
class OrderDetail extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'orders/{type}/{id}';

    protected static string $view = 'filament.pages.order-detail';

    public string $recordType = 'order';

    public int $recordId = 0;

    /** Section to scroll to on the combined overview (pricing | payment | decision). */
    public ?string $focus = null;

    #[Url(as: 'tab', except: 'overview')]
    public string $tab = 'overview';

    /** Admin pricing form state (see OrderDetailData::pricingState). Prices are indexed by column position. */
    public array $pricing = [];

    public bool $showPreview = false;

    /** Activity tab date filter (Y-m-d). */
    public string $activityFrom = '';

    public string $activityTo = '';

    public ?string $assignSalespersonId = null;

    public bool $showRejectForm = false;

    public string $rejectReason = '';

    public bool $showPaymentForm = false;

    public array $paymentForm = [];

    public $paymentReceipt = null;

    protected ?array $detailCache = null;

    public static function getRelativeRouteName(): string
    {
        return 'orders.show';
    }

    public static function urlFor(string $type, int $id): string
    {
        return static::getUrl(['type' => $type, 'id' => $id]);
    }

    public function mount(string $type, int $id): void
    {
        abort_unless(in_array($type, ['order', 'enquiry'], true), 404);

        $this->recordType = $type;
        $this->recordId = $id;

        // Overview, items & pricing and payment summary share one page; older links pass the section as the tab
        if (in_array($this->tab, ['pricing', 'payment', 'decision'], true)) {
            $this->focus = $this->tab;
            $this->tab = 'overview';
        } elseif (! in_array($this->tab, ['overview', 'documents', 'activity'], true)) {
            $this->tab = 'overview';
        }

        $detail = $this->detail();
        abort_unless($detail, 404);

        $this->initPricing($detail);
        $this->initPaymentForm($detail);

        // Section A: hold the enquiry while it is being prepared (released when sent / confirmed)
        $enquiry = $detail['enquiry'];
        if ($enquiry && auth()->user() && in_array($detail['stage']['key'], ['enquiry', 'pending_salesperson', 'quotation'], true) && ! $enquiry->acquireLock(auth()->user())) {
            Notification::make()->title('Currently being attended')->body(($enquiry->locker?->name ?? 'Another user').' is attending this order. It is read-only until they finish.')->warning()->send();
        }
    }

    public function getTitle(): string
    {
        return 'Order '.($this->detail()['number'] ?? '');
    }

    public function getHeading(): string
    {
        return '';
    }

    /** @return array<string, mixed>|null */
    public function detail(): ?array
    {
        return $this->detailCache ??= app(OrderDetailData::class)->for($this->recordType, $this->recordId);
    }

    protected function refreshDetail(): void
    {
        $this->detailCache = null;
        $this->initPricing($this->detail());
        $this->initPaymentForm($this->detail());
    }

    /** Polled while the enquiry is being prepared. */
    #[\Livewire\Attributes\Renderless]
    public function heartbeat(): void
    {
        $enquiry = $this->detail()['enquiry'] ?? null;

        if ($enquiry && auth()->user()) {
            $enquiry->heartbeat(auth()->user());
        }
    }

    public function resetActivityFilter(): void
    {
        $this->activityFrom = '';
        $this->activityTo = '';
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['pricing', 'payment', 'decision'], true)) {
            $this->tab = 'overview';
            $this->focus = $tab;
            $this->dispatch('og-scroll', id: 'og-section-'.$tab);

            return;
        }

        $this->tab = in_array($tab, ['overview', 'documents', 'activity'], true) ? $tab : 'overview';
        $this->focus = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Enquiry stage
    |--------------------------------------------------------------------------
    */

    public function approveEnquiry(): void
    {
        $enquiry = $this->detail()['enquiry'] ?? null;

        if (! $enquiry || $enquiry->isLockedByOther(auth()->user())) {
            return;
        }

        if ($enquiry->status === PortalEnquiryStatus::Pending) {
            $enquiry->update(['status' => PortalEnquiryStatus::InReview, 'attended_by' => $enquiry->attended_by ?? auth()->id(), 'attended_at' => $enquiry->attended_at ?? now()]);
            activity()->performedOn($enquiry)->causedBy(auth()->user())->log('Submitted order reviewed · approved for pricing');
        }

        $this->refreshDetail();
        $this->setTab('pricing');
        Notification::make()->title('Order reviewed · ready for pricing')->success()->send();
    }

    public function focusAssign(): void
    {
        $this->tab = 'overview';
        $this->dispatch('og-scroll', id: 'og-assign-salesperson');
    }

    public function assignSalesperson(): void
    {
        $enquiry = $this->detail()['enquiry'] ?? null;
        $order = $this->detail()['order'] ?? null;
        $salesperson = filled($this->assignSalespersonId) ? User::query()->find($this->assignSalespersonId) : null;

        if (! $salesperson) {
            Notification::make()->title('Select a salesperson first')->warning()->send();

            return;
        }

        // Order record without an enquiry (older admin entry): own it directly
        if (! $enquiry && $order) {
            $order->update(['salesperson_id' => $salesperson->id, 'sa_location_id' => $salesperson->sa_location_id ?? $order->sa_location_id]);
            QuotationStatusLog::query()->create([
                'quotation_id' => $order->id,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'user_id' => auth()->id(),
                'remarks' => 'Salesperson assigned: '.$salesperson->name,
            ]);
            Notification::make()->title('Salesperson assigned: '.$salesperson->name)->success()->send();
            $this->assignSalespersonId = null;
            $this->refreshDetail();

            return;
        }

        if (! $enquiry) {
            return;
        }

        try {
            app(AssignEnquirySalesperson::class)->execute($enquiry, $salesperson, auth()->user(), lock: false, source: $enquiry->source);
            Notification::make()->title('Salesperson assigned: '.$salesperson->name)->success()->send();
            $this->assignSalespersonId = null;
            $this->refreshDetail();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function toggleRejectForm(): void
    {
        $this->showRejectForm = ! $this->showRejectForm;
    }

    public function rejectEnquiry(): void
    {
        $this->validate(['rejectReason' => 'required|string|min:3|max:1000']);
        $enquiry = $this->detail()['enquiry'] ?? null;

        if (! $enquiry || $enquiry->isLockedByOther(auth()->user())) {
            return;
        }

        $payload = $enquiry->payload ?? [];
        $payload['rejection_reason'] = $this->rejectReason;
        $enquiry->update(['status' => PortalEnquiryStatus::Rejected, 'payload' => $payload]);
        activity()->performedOn($enquiry)->causedBy(auth()->user())->withProperties(['reason' => $this->rejectReason])->log('Order rejected at review');

        $this->rejectReason = '';
        $this->showRejectForm = false;
        $this->refreshDetail();
        Notification::make()->title('Order rejected')->success()->send();
    }

    /** Creates the order record(s) from the enquiry and opens the first one for pricing. */
    public function startPricing(): void
    {
        $enquiry = $this->detail()['enquiry'] ?? null;

        if (! $enquiry) {
            return;
        }

        if (! $enquiry->salesperson_id) {
            Notification::make()->title('Assign a salesperson first')->body('Every order must be owned by one salesperson before pricing.')->warning()->send();
            $this->focusAssign();

            return;
        }

        try {
            $orders = app(CreateOrderFromEnquiry::class)->execute($enquiry, auth()->user());
            $enquiry->releaseLock(auth()->user());

            Notification::make()
                ->title($orders->count().' order record(s) created under '.$enquiry->orderNumber())
                ->body($orders->pluck('number')->implode(', ').' · enter the charges to continue.')
                ->success()
                ->send();

            $this->redirect(static::urlFor('order', $orders->first()->id).'?tab=pricing', navigate: false);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Admin pricing
    |--------------------------------------------------------------------------
    */

    protected function initPricing(?array $detail): void
    {
        $order = $detail['order'] ?? null;

        if (! $order) {
            $this->pricing = [];

            return;
        }

        $state = app(OrderDetailData::class)->pricingState($order);
        $columns = $state['columns'];
        $hasOwner = $order->salesperson_id !== null;

        $state['rows'] = array_map(function (array $row) use ($columns, $state, $hasOwner) {
            $row['prices'] = array_map(fn (string $c) => $row['prices'][$c] ?? null, $columns);
            $row['list'] = array_map(fn (string $c) => $row['list'][$c] ?? ['price' => null, 'source' => null, 'tier' => null], $columns);

            // Not priced yet: offer the price-list rate once a salesperson owns the order
            if ($hasOwner && ($state['reference'] ?? 'price_list') !== 'manual' && collect($row['prices'])->filter(fn ($p) => filled($p) && (float) $p > 0)->isEmpty()) {
                $row['prices'] = array_map(fn (array $l) => $l['price'], $row['list']);
            }

            return $row;
        }, $state['rows']);

        if ($state['rows'] === [] && $columns !== []) {
            $state['rows'][] = [
                'line_type' => 'uom', 'catalog_key' => null, 'item_name' => '', 'uom' => null, 'quantity' => 1,
                'prices' => array_fill(0, count($columns), null),
                'list' => array_fill(0, count($columns), ['price' => null, 'source' => null, 'tier' => null]),
            ];
        }

        $this->pricing = $state;
    }

    /** @return array<string, string> */
    public function catalogOptions(string $type): array
    {
        return app(QuotationPricingLookup::class)->catalogOptionsForType($type ?: 'uom');
    }

    public function updatedPricing($value, string $key): void
    {
        if (! preg_match('/^rows\.(\d+)\.(line_type|catalog_key|quantity)$/', $key, $m)) {
            if ($key === 'reference') {
                $this->refreshAllRowPrices();
            }

            return;
        }

        $index = (int) $m[1];
        $field = $m[2];
        $row = &$this->pricing['rows'][$index];
        $lookup = app(QuotationPricingLookup::class);

        if ($field === 'quantity') {
            $row['quantity'] = max(1, (int) round((float) $value)); // whole units only
        }

        if ($field === 'line_type') {
            $row['catalog_key'] = null;
            $row['item_name'] = '';
            $row['uom'] = null;
            $row['quantity'] = 1;
            $row['prices'] = array_fill(0, count($this->pricing['columns']), null);
            $row['list'] = array_fill(0, count($this->pricing['columns']), ['price' => null, 'source' => null, 'tier' => null]);

            return;
        }

        if ($field === 'catalog_key') {
            $name = $lookup->resolveCatalogName($value);
            $row['item_name'] = $name ?? '';
            $row['uom'] = $row['line_type'] === 'uom' ? $lookup->resolveUomCode($value, $name) : null;
            if ($row['line_type'] !== 'uom') {
                $row['quantity'] = 1;
            }
        }

        $this->refreshRowPrices($index);
    }

    protected function refreshAllRowPrices(): void
    {
        foreach (array_keys($this->pricing['rows'] ?? []) as $index) {
            $this->refreshRowPrices($index);
        }
    }

    protected function refreshRowPrices(int $index): void
    {
        $row = &$this->pricing['rows'][$index];
        $columns = $this->pricing['columns'];
        $order = $this->detail()['order'] ?? null;

        if (blank($row['item_name'])) {
            return;
        }

        $list = app(OrderDetailData::class)->listPrices($order?->customer_id ? (int) $order->customer_id : null, $row['item_name'], $columns, (float) ($row['quantity'] ?: 1));
        $row['list'] = array_map(fn (string $c) => $list[$c] ?? ['price' => null, 'source' => null, 'tier' => null], $columns);

        if (($this->pricing['reference'] ?? 'price_list') !== 'manual') {
            $row['prices'] = array_map(fn (array $l) => $l['price'], $row['list']);
        }
    }

    public function addPricingRow(): void
    {
        $this->pricing['rows'][] = [
            'line_type' => 'uom',
            'catalog_key' => null,
            'item_name' => '',
            'uom' => null,
            'quantity' => 1,
            'prices' => array_fill(0, count($this->pricing['columns']), null),
            'list' => array_fill(0, count($this->pricing['columns']), ['price' => null, 'source' => null, 'tier' => null]),
        ];
    }

    public function removePricingRow(int $index): void
    {
        unset($this->pricing['rows'][$index]);
        $this->pricing['rows'] = array_values($this->pricing['rows']);
    }

    /** @return array{rows: list<float>, items: float, charges: float, total: float} */
    public function pricingTotals(): array
    {
        $rows = [];
        $items = 0.0;

        foreach ($this->pricing['rows'] ?? [] as $row) {
            $qty = ($row['line_type'] ?? 'item') === 'uom' ? max(0.01, (float) ($row['quantity'] ?: 1)) : 1.0;
            $line = 0.0;
            foreach ($row['prices'] ?? [] as $price) {
                if (filled($price)) {
                    $line += round((float) $price * $qty, 2);
                }
            }
            $rows[] = $line;
            $items += $line;
        }

        $charges = (float) ($this->pricing['pickup_charge'] ?: 0) + (float) ($this->pricing['drop_off_charge'] ?: 0) + (float) ($this->pricing['other_charges'] ?: 0);

        return ['rows' => $rows, 'items' => round($items, 2), 'charges' => round($charges, 2), 'total' => round($items + $charges, 2)];
    }

    public function savePricing(bool $preview = false): void
    {
        $order = $this->detail()['order'] ?? null;

        if (! $order) {
            return;
        }

        $columns = $this->pricing['columns'];
        $rows = array_map(fn (array $row) => [
            'line_type' => $row['line_type'],
            'item_name' => $row['item_name'],
            'catalog_key' => $row['catalog_key'],
            'quantity' => $row['quantity'],
            'prices' => collect($columns)->mapWithKeys(fn (string $c, int $i) => [$c => $row['prices'][$i] ?? null])->all(),
        ], $this->pricing['rows'] ?? []);

        try {
            $saved = app(SaveOrderPricing::class)->execute($order, auth()->user(), [
                'reference' => $this->pricing['reference'] ?? 'price_list',
                'columns' => $columns,
                'rows' => $rows,
                'pickup_charge' => $this->pricing['pickup_charge'] ?? null,
                'drop_off_charge' => $this->pricing['drop_off_charge'] ?? null,
                'other_charges' => $this->pricing['other_charges'] ?? null,
                'remarks' => $this->pricing['remarks'] ?? null,
                'override_reason' => $this->pricing['override_reason'] ?? null,
            ]);

            $this->refreshDetail();
            $this->showPreview = $preview && (float) $saved->total_amount > 0;
            $this->setTab('pricing');

            Notification::make()->title('Pricing saved · RM '.number_format((float) $saved->total_amount, 2))->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function openPreview(): void
    {
        $this->setTab('pricing');
        $this->showPreview = true;
    }

    public function backToPricing(): void
    {
        $this->showPreview = false;
    }

    /*
    |--------------------------------------------------------------------------
    | Payment summary (cash orders)
    |--------------------------------------------------------------------------
    */

    protected function initPaymentForm(?array $detail): void
    {
        $order = $detail['order'] ?? null;
        $outstanding = $order ? max(0, (float) $order->total_amount - (float) $order->paid_amount) : 0;

        $this->paymentForm = [
            'type' => 'full',
            'amount' => $outstanding > 0 ? number_format($outstanding, 2, '.', '') : null,
            'payment_date' => now()->toDateString(),
            'method' => $order?->payment_method ?: PaymentMethod::BankTransfer->value,
            'reference' => null,
            'bank_account' => null,
            'remarks' => null,
        ];
        $this->paymentReceipt = null;
    }

    public function updatedPaymentForm($value, string $key): void
    {
        if ($key === 'type') {
            $order = $this->detail()['order'] ?? null;
            $outstanding = $order ? max(0, (float) $order->total_amount - (float) $order->paid_amount) : 0;
            $this->paymentForm['amount'] = $value === 'full' ? number_format($outstanding, 2, '.', '') : null;
        }
    }

    public function togglePaymentForm(): void
    {
        $this->showPaymentForm = ! $this->showPaymentForm;
    }

    /** Records a customer payment on the counter / from a slip and approves it when the actor may. */
    public function recordPayment(): void
    {
        $order = $this->detail()['order'] ?? null;

        if (! $order) {
            return;
        }

        $this->validate([
            'paymentForm.type' => 'required|in:full,partial',
            'paymentForm.amount' => 'required|numeric|min:0.01',
            'paymentForm.payment_date' => 'required|date',
            'paymentForm.method' => 'required|string',
            'paymentForm.reference' => 'nullable|string|max:100',
            'paymentForm.remarks' => 'nullable|string|max:1000',
            'paymentReceipt' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ]);

        try {
            $receiptPath = $this->paymentReceipt?->store('payment-receipts/'.$order->id, 'public');

            $submission = app(SubmitPaymentEvidence::class)->execute($order, [
                'amount' => $this->paymentForm['amount'],
                'method' => $this->paymentForm['method'],
                'payment_date' => $this->paymentForm['payment_date'],
                'bank_account' => $this->paymentForm['bank_account'] ?? null,
                'reference' => $this->paymentForm['reference'] ?: null,
                'receipt_path' => $receiptPath,
                'remarks' => trim(($this->paymentForm['type'] === 'partial' ? 'Partial payment. ' : 'Full payment. ').($this->paymentForm['remarks'] ?? '')),
            ], auth()->user(), 'admin');

            $message = 'Payment recorded · RM '.number_format((float) $submission->amount, 2);

            try {
                $review = app(ReviewPaymentSubmission::class);

                if ($submission->requiresTwoApprovals()) {
                    $submission = $review->verify($submission, auth()->user());
                    $message .= ' · verified (level 1) · a second user must approve';
                } else {
                    $result = $review->approve($submission, auth()->user());
                    $message .= ' · approved';

                    if (($result['billing']['ok'] ?? null) === true) {
                        $message .= ' · Cash Bill issued and CSN created';
                    } elseif (($result['billing']['ok'] ?? null) === false) {
                        $message .= ' · billing failed: '.($result['billing']['error'] ?? 'see activity');
                    }
                }
            } catch (Throwable $e) {
                $message .= ' · awaiting approval ('.$e->getMessage().')';
            }

            Notification::make()->title($message)->success()->send();
            $this->showPaymentForm = false;
            $this->refreshDetail();
            $this->setTab('payment');
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function verifyPayment(int $submissionId): void
    {
        $this->reviewPayment($submissionId, 'verify');
    }

    public function approvePayment(int $submissionId): void
    {
        $this->reviewPayment($submissionId, 'approve');
    }

    protected function reviewPayment(int $submissionId, string $what): void
    {
        $submission = PaymentSubmission::query()->find($submissionId);

        if (! $submission) {
            return;
        }

        try {
            if ($what === 'verify') {
                app(ReviewPaymentSubmission::class)->verify($submission, auth()->user());
                Notification::make()->title('Payment verified (level 1)')->success()->send();
            } else {
                $result = app(ReviewPaymentSubmission::class)->approve($submission, auth()->user());
                $billing = $result['billing'];
                Notification::make()
                    ->title('Payment approved')
                    ->body($billing ? ($billing['ok'] ? 'Cash Bill issued and CSN created.' : 'Billing failed: '.$billing['error']) : null)
                    ->success()
                    ->send();
            }

            $this->refreshDetail();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Order actions (Filament modals)
    |--------------------------------------------------------------------------
    */

    protected function order(): ?Quotation
    {
        return $this->detail()['order'] ?? null;
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label('Send for confirmation')
            ->modalHeading('Send quotation to customer')
            ->modalDescription(fn () => 'The customer receives the quotation link to accept or reject. Price offered: RM '.number_format((float) ($this->order()?->total_amount ?? 0), 2).'.')
            ->form([
                Forms\Components\CheckboxList::make('channels')
                    ->label('Channels')
                    ->options([
                        NotificationLog::CHANNEL_EMAIL => 'Email (portal link)',
                        NotificationLog::CHANNEL_WHATSAPP => 'WhatsApp (share link)',
                    ])
                    ->default([NotificationLog::CHANNEL_EMAIL, NotificationLog::CHANNEL_WHATSAPP])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $order = $this->order();

                try {
                    $result = app(SendQuotation::class)->execute($order, auth()->user(), $data['channels']);
                    $this->logOfferedPrice($order, 'Quotation v'.$order->version.' sent to customer via '.implode(', ', $data['channels']), 'sent', implode(' + ', array_map('ucfirst', $data['channels'])));
                    $order->portalEnquiry?->releaseLock(auth()->user());
                    $wa = $order->notificationLogs()->where('channel', 'whatsapp')->latest('id')->first();

                    Notification::make()
                        ->title(in_array($result->status, [QuotationStatus::Confirmed, QuotationStatus::Accepted], true) ? 'Quotation sent and auto-accepted (consent letter on file)' : 'Quotation sent to customer')
                        ->body($wa?->whatsapp_url ? 'WhatsApp message ready — open it from the Notifications log.' : null)
                        ->success()
                        ->send();

                    $this->showPreview = false;
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Customer accepted')
            ->color('success')
            ->modalHeading('Customer accepted the quotation')
            ->modalDescription(fn () => 'Confirms '.$this->order()?->number.' at RM '.number_format((float) ($this->order()?->total_amount ?? 0), 2).'. A proforma invoice is issued; non-cash orders continue straight to invoice and CSN.')
            ->form([
                Forms\Components\Select::make('channel')
                    ->label('Confirmation channel')
                    ->options([
                        AcceptQuotation::CHANNEL_WHATSAPP => 'WhatsApp',
                        AcceptQuotation::CHANNEL_EMAIL => 'Email',
                        AcceptQuotation::CHANNEL_ADMIN => 'Counter / phone (admin recorded)',
                    ])
                    ->default(AcceptQuotation::CHANNEL_WHATSAPP)
                    ->required(),
                Forms\Components\TextInput::make('confirmed_by_name')->label('Confirmed by (customer contact)')->required(),
                Forms\Components\Textarea::make('consent_evidence')->label('Evidence / remarks')->placeholder('e.g. WhatsApp confirmation received 1 Oct 10:15 from +60 12-345 6789'),
            ])
            ->action(function (array $data): void {
                try {
                    $this->logOfferedPrice($this->order(), 'Customer accepted quotation v'.$this->order()->version.' via '.$data['channel'].' ('.$data['confirmed_by_name'].')', 'accepted', ucfirst((string) $data['channel']).' · '.$data['confirmed_by_name']);
                    $result = app(AcceptQuotation::class)->execute($this->order(), $data['channel'], $data['confirmed_by_name'], auth()->user(), $data['consent_evidence'] ?? null);
                    Notification::make()->title('Customer confirmation recorded')->body('Status: '.$result->status->getLabel().'. Proforma '.$result->proformaInvoice?->number.' generated.')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Customer rejected')
            ->color('danger')
            ->modalHeading('Customer rejected the quotation')
            ->modalDescription(fn () => 'Record why the customer rejected '.$this->order()?->number.' (RM '.number_format((float) ($this->order()?->total_amount ?? 0), 2).'). The reason is kept in the order activity.')
            ->form([
                Forms\Components\Select::make('category')->label('Reason')->options(QuotationRejectionCategory::options())->required(),
                Forms\Components\Textarea::make('reason')->label('Details')->required()->placeholder('e.g. Price too high for 12 CTN, customer expects RM 8.00 per unit'),
                Forms\Components\Toggle::make('return_to_edit')
                    ->label('Return the order to editing so it can be re-priced and sent again')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $order = $this->order();

                try {
                    $offered = (float) $order->total_amount;
                    $category = QuotationRejectionCategory::from($data['category']);
                    $result = app(RejectQuotation::class)->execute($order, $category, $data['reason'], auth()->user(), 'admin');

                    QuotationStatusLog::query()->create([
                        'quotation_id' => $order->id,
                        'from_status' => $result->status->value,
                        'to_status' => $result->status->value,
                        'user_id' => auth()->id(),
                        'remarks' => sprintf('Customer rejected quotation v%d (RM %s) · %s · %s', $order->version, number_format($offered, 2), $category->getLabel(), $data['reason']),
                        'meta' => [
                            'offer_event' => 'rejected',
                            'version' => $order->version,
                            'total' => round($offered, 2),
                            'reason' => $category->getLabel().' — '.$data['reason'],
                            'lines' => [],
                        ],
                    ]);

                    if (($data['return_to_edit'] ?? true) && $result->status !== QuotationStatus::Negotiation) {
                        $from = $result->status->value;
                        $result->update(['status' => QuotationStatus::Negotiation]);

                        QuotationStatusLog::query()->create([
                            'quotation_id' => $order->id,
                            'from_status' => $from,
                            'to_status' => QuotationStatus::Negotiation->value,
                            'user_id' => auth()->id(),
                            'remarks' => 'Order returned to editing after customer rejection',
                        ]);
                    }

                    Notification::make()
                        ->title(($data['return_to_edit'] ?? true) ? 'Rejection recorded · order is editable again' : 'Rejection recorded · order closed')
                        ->success()
                        ->send();

                    $this->refreshDetail();
                    $this->setTab('pricing');
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function reviseAction(): Action
    {
        return Action::make('revise')
            ->label('Revise (new version)')
            ->form([Forms\Components\Textarea::make('reason')->label('Reason for revision')->required()])
            ->action(function (array $data): void {
                try {
                    $revision = app(ReviseQuotation::class)->execute($this->order(), auth()->user(), $data['reason']);
                    Notification::make()->title('Version '.$revision->version.' created')->success()->send();
                    $this->redirect(static::urlFor('order', $revision->id).'?tab=pricing', navigate: false);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function changeOrderTypeAction(): Action
    {
        return Action::make('changeOrderType')
            ->label('Change payment term')
            ->form(fn () => [
                Forms\Components\Placeholder::make('current')->label('Current')->content($this->order()?->orderType()?->getLabel() ?? 'Not set'),
                Forms\Components\Select::make('order_type')
                    ->label('New payment term')
                    ->options(function () {
                        $order = $this->order();
                        $current = $order?->orderType();
                        $allowed = $current ? $current->allowedTransitions() : OrderType::cases();

                        return collect($allowed)
                            ->reject(fn (OrderType $t) => $t === OrderType::Term && ! $order?->customer?->is_credit)
                            ->mapWithKeys(fn (OrderType $t) => [$t->value => $t->getLabel()]);
                    })
                    ->required()
                    ->helperText('Term → Term / Cash / COD · COD → Cash only · Cash cannot change.'),
                Forms\Components\Textarea::make('reason')->label('Reason'),
            ])
            ->action(function (array $data): void {
                try {
                    app(ChangeOrderType::class)->execute($this->order(), OrderType::from($data['order_type']), auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Payment term updated')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function releaseAction(): Action
    {
        return Action::make('release')
            ->label('Admin release')
            ->color('warning')
            ->modalHeading('Admin release')
            ->form(fn () => [
                Forms\Components\Placeholder::make('summary')->label('Order')->content(sprintf('%s · %s · Total RM %s · Paid RM %s · Outstanding RM %s',
                    $this->order()?->number,
                    $this->order()?->orderType()?->getLabel() ?? '—',
                    number_format((float) $this->order()?->total_amount, 2),
                    number_format((float) $this->order()?->paid_amount, 2),
                    number_format($this->order()?->outstandingAmount() ?? 0, 2))),
                Forms\Components\Textarea::make('reason')->label('Release reason / collection plan')->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $result = app(ReleaseOrder::class)->execute($this->order(), auth()->user(), $data['reason']);
                    Notification::make()->title($result['ok'] ? 'Order released — billing issued and CSN created' : 'Order released, but billing failed')->body($result['error'])->{$result['ok'] ? 'success' : 'danger'}()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function blockCodAction(): Action
    {
        return Action::make('blockCod')
            ->label('Block COD order')
            ->color('danger')
            ->form([Forms\Components\Textarea::make('reason')->required()])
            ->action(function (array $data): void {
                try {
                    app(ReleaseOrder::class)->blockCod($this->order(), auth()->user(), $data['reason']);
                    Notification::make()->title('COD order blocked')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function unblockCodAction(): Action
    {
        return Action::make('unblockCod')
            ->label('Unblock COD order')
            ->form([Forms\Components\Textarea::make('reason')->label('Reason (optional)')])
            ->action(function (array $data): void {
                try {
                    $result = app(ReleaseOrder::class)->unblockCod($this->order(), auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title($result['ok'] ? 'COD order unblocked' : 'Unblocked, but billing failed')->body($result['error'])->{$result['ok'] ? 'success' : 'danger'}()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function generateBillingAction(): Action
    {
        return Action::make('generateBilling')
            ->label(fn () => $this->order()?->consignmentNotes()->exists() ? 'Create CSN' : 'Generate Invoice / Cash Bill → CSN')
            ->requiresConfirmation()
            ->modalDescription(fn () => $this->order()?->billingBlockReason() ?? ($this->order()?->billing_error ? 'Last error: '.$this->order()->billing_error : 'Invoice / Cash Bill will be issued and the CSN created as Pending Lorry Assignment.'))
            ->action(function (): void {
                try {
                    $result = app(GenerateOrderBilling::class)->execute($this->order(), auth()->user());
                    Notification::make()
                        ->title($result['ok'] ? 'Billing issued: '.$result['invoices']->pluck('number')->implode(', ').' · CSN: '.$result['csns']->pluck('number')->implode(', ') : 'Billing generation failed')
                        ->body($result['error'])
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reopen closed case')
            ->requiresConfirmation()
            ->action(function (): void {
                app(ClosePendingCustomerReviews::class)->reopen($this->order(), auth()->user());
                Notification::make()->title('Case reopened as draft')->success()->send();
                $this->refreshDetail();
            });
    }

    public function creditDecisionAction(): Action
    {
        return Action::make('creditDecision')
            ->label('Credit approval')
            ->modalHeading('Credit approval decision')
            ->form(fn () => [
                Forms\Components\Placeholder::make('reason')->label('Why approval is needed')->content(CreditApprovalRequest::query()->where('quotation_id', $this->order()?->id)->where('status', 'pending')->value('reason') ?? '—'),
                Forms\Components\Radio::make('decision')->options(['approve' => 'Approve credit · proceed to invoice & CSN', 'reject' => 'Reject'])->default('approve')->required(),
                Forms\Components\Textarea::make('remarks')->label('Remarks'),
            ])
            ->action(function (array $data): void {
                $request = CreditApprovalRequest::query()->where('quotation_id', $this->order()?->id)->where('status', 'pending')->first();

                if (! $request) {
                    Notification::make()->title('No pending credit approval request')->warning()->send();

                    return;
                }

                try {
                    app(DecideCreditApproval::class)->execute($request, auth()->user(), $data['decision'] === 'approve', $data['remarks'] ?? null);
                    Notification::make()->title($data['decision'] === 'approve' ? 'Credit approved · order proceeds to invoice and CSN' : 'Credit rejected')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function rejectPaymentAction(): Action
    {
        return Action::make('rejectPayment')
            ->label('Reject payment')
            ->color('danger')
            ->form([Forms\Components\Textarea::make('reason')->label('Reason (sent to the customer)')->required()])
            ->action(function (array $data, array $arguments): void {
                $submission = PaymentSubmission::query()->find($arguments['id'] ?? 0);

                if (! $submission) {
                    return;
                }

                try {
                    app(ReviewPaymentSubmission::class)->reject($submission, auth()->user(), $data['reason']);
                    Notification::make()->title('Payment rejected · customer notified')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public function sendInvoiceAction(): Action
    {
        return Action::make('sendInvoice')
            ->label('Send invoice / cash bill')
            ->form(fn () => [
                Forms\Components\TextInput::make('to_email')->label('Send to')->email()->default($this->order()?->customer?->email),
                Forms\Components\Textarea::make('note')->label('Note'),
            ])
            ->action(function (array $data): void {
                $invoice = $this->order()?->invoices()->latest('id')->first();

                if (! $invoice) {
                    return;
                }

                try {
                    app(SendInvoice::class)->execute($invoice, auth()->user(), $data['to_email'] ?: null, $data['note'] ?? null);
                    Notification::make()->title('Invoice sent')->success()->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /** Activity record of the exact price offered to the customer (per line). */
    protected function logOfferedPrice(Quotation $order, string $prefix, string $event = 'sent', ?string $channel = null): void
    {
        $order->loadMissing(['lines', 'destinations']);
        $byDestination = $order->destinations->keyBy('id');

        $structured = $order->lines->map(fn ($line) => [
            'item' => $line->item_name,
            'qty' => rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.'),
            'unit' => round((float) $line->unit_price, 2),
            'amount' => round((float) $line->line_total, 2),
            'destination' => $byDestination->get($line->quotation_destination_id)?->consignee_name,
        ])->values()->all();

        $lines = collect($structured)->map(fn (array $l) => sprintf('%s × %s @ RM %s = RM %s%s',
            $l['item'], $l['qty'], number_format($l['unit'], 2), number_format($l['amount'], 2),
            $l['destination'] ? ' ('.$l['destination'].')' : ''))->implode('; ');

        QuotationStatusLog::query()->create([
            'quotation_id' => $order->id,
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'user_id' => auth()->id(),
            'remarks' => $prefix.' · price offered RM '.number_format((float) $order->total_amount, 2).' · '.$lines,
            'meta' => [
                'offer_event' => $event,
                'version' => $order->version,
                'channel' => $channel,
                'total' => round((float) $order->total_amount, 2),
                'lines' => $structured,
            ],
        ]);
    }

    /** @return array<int|string, string> */
    public function salespersonOptions(): array
    {
        return User::query()->role('salesperson')->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.($u->saLocation ? ' · '.$u->saLocation->code : '')])
            ->all();
    }

    /** @return array<string, string> */
    public function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }
}
