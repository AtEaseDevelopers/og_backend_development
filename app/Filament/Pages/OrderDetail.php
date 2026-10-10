<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\EditRecordedPayment;
use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\Billing\Actions\RefreshOrderPaidAmount;
use App\Domains\Billing\Actions\ReviewPaymentSubmission;
use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Billing\Models\Payment;
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
use App\Domains\Quotation\Actions\SendOrderDocument;
use App\Domains\Quotation\Actions\SaveOrderPricing;
use App\Domains\Quotation\Actions\SendQuotation;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Order detail: header with the 7-step progress, the action banner and the Overview (customer & order,
 * record ownership, payment summary, linked records) / Items & pricing / Activity tabs.
 * Older links to the former Payment summary and Documents tabs land on the overview card that replaced them.
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

    /** Section to scroll to on the combined overview (pricing | payment | decision | documents). */
    public ?string $focus = null;

    /** Former tabs that are now a section / card of the overview (old links and banner CTAs still pass them). */
    public const OVERVIEW_SECTIONS = ['pricing', 'payment', 'decision', 'documents'];

    #[Url(as: 'tab', except: 'overview')]
    public string $tab = 'overview';

    /** Admin pricing form state (see OrderDetailData::pricingState). Prices are indexed by column position. */
    public array $pricing = [];

    public bool $showPreview = false;

    /** Activity tab date filter (Y-m-d). */
    public string $activityFrom = '';

    public string $activityTo = '';

    public ?string $assignSalespersonId = null;

    /** Record ownership card in edit mode: the salesperson becomes a dropdown (Save / Cancel at the top right). */
    public bool $editingOwnership = false;

    /** Customer & order card in edit mode: payment term, DO number and expected delivery date. */
    public bool $editingDetails = false;

    /** @var array{order_type?: string, do_number?: string, expected_delivery?: string} */
    public array $details = [];

    public bool $showRejectForm = false;

    public string $rejectReason = '';

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

        // Overview, items & pricing, payment summary and linked records (documents) share one page;
        // older links (?tab=payment, ?tab=documents, …) pass the section as the tab and land on its card
        if (in_array($this->tab, self::OVERVIEW_SECTIONS, true)) {
            $this->focus = $this->tab;
            $this->tab = 'overview';
        } elseif (! in_array($this->tab, ['overview', 'activity'], true)) {
            $this->tab = 'overview';
        }

        $detail = $this->detail();
        abort_unless($detail, 404);

        $this->initPricing($detail);

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
        // payment → Payment summary card, documents → Linked records card (both on the overview)
        if (in_array($tab, self::OVERVIEW_SECTIONS, true)) {
            $this->tab = 'overview';
            $this->focus = $tab;
            $this->dispatch('og-scroll', id: 'og-section-'.$tab);

            return;
        }

        $this->tab = in_array($tab, ['overview', 'activity'], true) ? $tab : 'overview';
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

    /** An old version (replaced by a newer one) is view only. */
    protected function isOldVersion(): bool
    {
        $order = $this->detail()['order'] ?? null;

        return $order instanceof Quotation && ! $order->isLatestVersion();
    }

    protected function refuseOnOldVersion(): bool
    {
        if (! $this->isOldVersion()) {
            return false;
        }

        Notification::make()->title('This is an old version · view only')->body('Open the latest version to make changes.')->warning()->send();

        return true;
    }

    public function mountAction(string $name, array $arguments = []): mixed
    {
        if ($this->refuseOnOldVersion()) {
            return null;
        }

        return parent::mountAction($name, $arguments);
    }

    public function focusAssign(): void
    {
        $this->tab = 'overview';
        $this->editOwnership();
        $this->dispatch('og-scroll', id: 'og-assign-salesperson');
    }

    /*
    |--------------------------------------------------------------------------
    | Inline edit: Record ownership (salesperson) and Customer & order
    |--------------------------------------------------------------------------
    */

    public function editOwnership(): void
    {
        if ($this->refuseOnOldVersion() || ! ($this->detail()['can']['assign_salesperson'] ?? false)) {
            return;
        }

        $this->assignSalespersonId = (string) ($this->currentSalespersonId() ?? '');
        $this->editingOwnership = true;
    }

    public function cancelOwnership(): void
    {
        $this->editingOwnership = false;
        $this->assignSalespersonId = null;
    }

    /** Save of the Record ownership card: the salesperson picked (audited, see assignSalesperson); unchanged = nothing to do. */
    public function saveOwnership(): void
    {
        if ((string) $this->assignSalespersonId === (string) ($this->currentSalespersonId() ?? '')) {
            $this->cancelOwnership();

            return;
        }

        $this->assignSalesperson();

        // assigned: assignSalesperson clears the picked id
        if ($this->assignSalespersonId === null) {
            $this->editingOwnership = false;
        }
    }

    protected function currentSalespersonId(): ?int
    {
        $detail = $this->detail();
        $id = $detail['order']?->salesperson_id ?? $detail['enquiry']?->salesperson_id ?? null;

        return $id ? (int) $id : null;
    }

    public function editDetails(): void
    {
        $order = $this->order();
        $can = $this->detail()['can'] ?? [];

        if (! $order || $this->refuseOnOldVersion() || ! (($can['edit_details'] ?? false) || ($can['change_type'] ?? false))) {
            return;
        }

        $this->details = [
            'order_type' => (string) ($order->orderType()?->value ?? ''),
            'do_number' => (string) ($order->customer_do_number ?? ''),
            'expected_delivery' => (string) ($order->expected_delivery_date ?? ''),
        ];
        $this->resetErrorBag();
        $this->editingDetails = true;
    }

    public function cancelDetails(): void
    {
        $this->editingDetails = false;
        $this->details = [];
        $this->resetErrorBag();
    }

    /**
     * Payment terms the order can change to (Quotation::allowedOrderTypes: any until the customer confirms or a
     * payment is recorded), its current one first; Credit / Term only for a credit customer.
     *
     * @return array<string, string>
     */
    public function paymentTermOptions(): array
    {
        $order = $this->order();
        $current = $order?->orderType();
        $allowed = $order ? $order->allowedOrderTypes() : OrderType::cases();

        return collect($current ? [$current, ...$allowed] : $allowed)
            ->unique(fn (OrderType $t) => $t->value)
            ->reject(fn (OrderType $t) => $t === OrderType::Term && $t !== $current && ! $order?->customer?->is_credit)
            ->mapWithKeys(fn (OrderType $t) => [$t->value => $t->getLabel()])
            ->all();
    }

    /** Save of the Customer & order card: DO number / expected delivery date on the record, then the payment term (ChangeOrderType). */
    public function saveDetails(): void
    {
        $order = $this->order();
        $can = $this->detail()['can'] ?? [];

        if (! $order || $this->refuseOnOldVersion()) {
            return;
        }

        $rules = [];

        if ($can['edit_details'] ?? false) {
            $rules['details.do_number'] = 'required|string|max:100';
            $rules['details.expected_delivery'] = 'nullable|string|max:255';
        }

        if ($can['change_type'] ?? false) {
            $rules['details.order_type'] = 'required|in:'.implode(',', array_keys($this->paymentTermOptions()));
        }

        $this->validate($rules, [], [
            'details.do_number' => 'DO number',
            'details.expected_delivery' => 'expected delivery date',
            'details.order_type' => 'payment term',
        ]);

        $changes = [];

        try {
            if ($can['edit_details'] ?? false) {
                $do = trim((string) ($this->details['do_number'] ?? ''));
                $delivery = trim((string) ($this->details['expected_delivery'] ?? ''));
                $fields = [];

                if ($do !== trim((string) $order->customer_do_number)) {
                    $fields['customer_do_number'] = $do;
                    $changes[] = 'DO number '.($order->customer_do_number ?: '—').' → '.$do;
                }

                if ($delivery !== trim((string) $order->expected_delivery_date)) {
                    $fields['expected_delivery_date'] = $delivery !== '' ? $delivery : null;
                    $changes[] = 'expected delivery '.($order->expected_delivery_date ?: '—').' → '.($delivery !== '' ? $delivery : '—');
                }

                if ($fields !== []) {
                    $order->update($fields);
                    QuotationStatusLog::query()->create([
                        'quotation_id' => $order->id,
                        'from_status' => $order->status->value,
                        'to_status' => $order->status->value,
                        'user_id' => auth()->id(),
                        'remarks' => 'Order details edited: '.implode(' · ', $changes),
                    ]);
                }
            }

            $type = OrderType::tryFrom((string) ($this->details['order_type'] ?? ''));

            if (($can['change_type'] ?? false) && $type && $type !== $order->orderType()) {
                app(ChangeOrderType::class)->execute($order->fresh(), $type, auth()->user());
                $changes[] = 'payment term → '.$type->getLabel();
            }
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->cancelDetails();
        $this->refreshDetail();
        Notification::make()->title($changes === [] ? 'Nothing changed' : 'Order details saved')->body($changes === [] ? null : ucfirst(implode(' · ', $changes)))->success()->send();
    }

    public function assignSalesperson(): void
    {
        if ($this->refuseOnOldVersion()) {
            return;
        }

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
        if ($this->refuseOnOldVersion()) {
            return;
        }

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
        if ($this->refuseOnOldVersion()) {
            return;
        }

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
    | Payment summary (add / edit / review payments, any order type and stage)
    |--------------------------------------------------------------------------
    */

    /**
     * Records a payment (counter / slip) through the same flow as the customer portal: SubmitPaymentEvidence, then
     * approved at once when the actor may review payments (Cash / Pay at Counter are verified and need a second user).
     * Approval recomputes paid / outstanding, issues a refund note on overpayment and bills a fully paid cash order.
     *
     * @param  array{amount: mixed, method: string, payment_date: string, reference?: ?string, attachment?: ?string, remarks?: ?string}  $data
     */
    protected function storePayment(Quotation $order, array $data): string
    {
        // a credit term order is paid against its invoice (Invoices / AR), not here (same rule as payment_summary.can_add)
        if ($order->orderType() === OrderType::Term) {
            throw new InvalidArgumentException('Credit term orders are paid against their invoice. Record the payment under Billing → Invoices.');
        }

        $amount = round((float) $data['amount'], 2);
        $outstanding = RefreshOrderPaidAmount::liveOutstanding($order);
        $kind = match (true) {
            $outstanding <= 0.004 => 'Additional payment.',
            $amount + 0.005 >= $outstanding => 'Full payment.',
            default => 'Partial payment.',
        };

        $submission = app(SubmitPaymentEvidence::class)->execute($order, [
            'amount' => $amount,
            'method' => $data['method'],
            'payment_date' => $data['payment_date'],
            'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
            'receipt_path' => EditRecordedPayment::paths($data['attachment'] ?? null)[0] ?? null,
            'receipt_paths' => EditRecordedPayment::paths($data['attachment'] ?? null) ?: null,
            'remarks' => trim($kind.' '.($data['remarks'] ?? '')),
        ], auth()->user(), 'admin');

        $message = 'Payment recorded · RM '.number_format((float) $submission->amount, 2);

        try {
            $review = app(ReviewPaymentSubmission::class);

            if ($submission->requiresTwoApprovals()) {
                $review->verify($submission, auth()->user());
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

        return $message;
    }

    public function addPaymentAction(): Action
    {
        return Action::make('addPayment')
            ->label('Add payment')
            ->modalHeading('Add payment')
            ->modalDescription(fn () => $this->paymentTotalsLine())
            ->modalSubmitActionLabel('Record payment')
            ->visible(fn () => (bool) ($this->detail()['payment_summary']['can_add'] ?? false))
            ->fillForm(fn () => [
                'amount' => ($outstanding = ($order = $this->order()) ? RefreshOrderPaidAmount::liveOutstanding($order) : 0.0) > 0 ? number_format($outstanding, 2, '.', '') : null,
                'payment_date' => now()->toDateString(),
                'method' => PaymentMethod::tryFrom((string) $this->order()?->payment_method)?->value ?? PaymentMethod::BankTransfer->value,
            ])
            ->form(fn () => [
                Forms\Components\Placeholder::make('add_note')
                    ->hiddenLabel()
                    ->content(fn () => $this->addPaymentNote()),
                ...$this->paymentFormSchema(),
            ])
            ->action(function (array $data, Action $action): void {
                $order = $this->order();
                $error = null;

                try {
                    $message = $order ? $this->storePayment($order, $data) : null;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }

                if ($error !== null || ! $order) {
                    Notification::make()->title($error ?? 'Order not found')->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title($message)->success()->send();
                $this->refreshDetail();
            });
    }

    /** Edit a recorded payment at any stage (also after billing / CSN): amount either way, method, date, reference, slip, note. */
    public function editPaymentAction(): Action
    {
        return Action::make('editPayment')
            ->label('Edit payment')
            ->modalHeading('Edit payment')
            ->modalDescription(fn () => $this->paymentTotalsLine())
            ->modalSubmitActionLabel('Save changes')
            ->visible(fn () => $this->order() !== null && EditRecordedPayment::allows(auth()->user()))
            ->fillForm(function (array $arguments): array {
                [$submission, $payment] = $this->paymentRecord($arguments);
                $current = EditRecordedPayment::snapshot($submission, $payment);

                return [
                    'amount' => $current['amount'],
                    'payment_date' => $current['payment_date'],
                    'method' => $current['method'],
                    'reference' => $current['reference'],
                    'attachment' => $current['attachment'],
                    'remarks' => $current['remarks'],
                ];
            })
            ->form(function (array $arguments): array {
                [$submission, $payment] = $this->paymentRecord($arguments);
                $order = $this->order();
                $documents = $order ? EditRecordedPayment::documentsFor($order, $submission, $payment) : [];

                return [
                    Forms\Components\Placeholder::make('issued_documents')
                        ->hiddenLabel()
                        ->visible($documents !== [])
                        ->content(new HtmlString(
                            '<div class="ow-modal-warning" role="alert"><strong>Already-issued documents are not changed automatically.</strong> '
                            .'Paid and outstanding amounts are recalculated, but these stay as issued — adjust or re-issue them separately if needed:'
                            .'<ul>'.collect($documents)->map(fn (string $doc) => '<li>'.e($doc).'</li>')->implode('').'</ul></div>'
                        )),
                    ...$this->paymentFormSchema(hasDate: $submission !== null),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for change')
                        ->required()
                        ->rows(2)
                        ->maxLength(1000)
                        ->placeholder('e.g. Customer paid RM 200 only, the full amount was keyed in by mistake'),
                ];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $order = $this->order();
                [$submission, $payment] = $this->paymentRecord($arguments);
                $record = $submission ?? $payment;
                $error = $order && $record ? null : 'This payment was not found on the order.';
                $result = null;

                // the upload field drops a stored path whose file is missing on disk: keep those references as they were
                $attachment = EditRecordedPayment::paths($data['attachment'] ?? null);
                $original = EditRecordedPayment::snapshot($submission, $payment)['attachment'];

                foreach ($original as $path) {
                    if (! in_array($path, $attachment, true) && ! Storage::disk('public')->exists($path)) {
                        $attachment[] = $path;
                    }
                }

                if ($error === null) {
                    try {
                        $result = app(EditRecordedPayment::class)->execute($order, $record, [
                            'amount' => $data['amount'] ?? null,
                            'method' => $data['method'] ?? null,
                            'payment_date' => $data['payment_date'] ?? null,
                            'reference' => $data['reference'] ?? null,
                            'attachment_paths' => $attachment,
                            'remarks' => $data['remarks'] ?? null,
                        ], (string) ($data['reason'] ?? ''), auth()->user());
                    } catch (Throwable $e) {
                        $error = $e->getMessage();
                    }
                }

                if ($error !== null) {
                    Notification::make()->title($error)->danger()->send();
                    $action->halt();

                    return;
                }

                $this->refreshDetail();
                $fresh = $this->order();
                $billing = $result['billing'];

                Notification::make()
                    ->title(sprintf('Payment updated · Paid RM %s · Outstanding RM %s',
                        number_format($fresh ? RefreshOrderPaidAmount::livePaid($fresh) : 0, 2),
                        number_format($fresh ? RefreshOrderPaidAmount::liveOutstanding($fresh) : 0, 2)))
                    ->body(collect([
                        ...($result['warnings'] ?? []),
                        $result['documents'] !== [] ? 'Not changed: '.implode('; ', $result['documents']).'.' : null,
                        $billing ? ($billing['ok'] ? 'Fully paid · Cash Bill issued and CSN created.' : 'Billing failed: '.$billing['error']) : null,
                    ])->filter()->implode(' ') ?: null)
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, Forms\Components\Component> */
    protected function paymentFormSchema(bool $hasDate = true): array
    {
        return [
            Forms\Components\Grid::make(['default' => 1, 'sm' => 2])->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->prefix('RM')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Payment date')
                    ->required($hasDate)
                    ->maxDate(now()->endOfDay())
                    ->visible($hasDate),
                Forms\Components\Select::make('method')
                    ->label('Method')
                    ->options(PaymentMethod::options())
                    ->required(),
                Forms\Components\TextInput::make('reference')
                    ->label('Reference no.')
                    ->maxLength(100)
                    ->placeholder('Bank / slip reference'),
            ]),
            Forms\Components\FileUpload::make('attachment')
                ->label('Payment slips / receipts')
                ->multiple()
                ->appendFiles()
                ->maxFiles(10)
                ->panelLayout('grid')
                ->itemPanelAspectRatio(1)
                ->imagePreviewHeight('80')
                ->extraAttributes(['class' => 'ow-slip-upload'])
                ->disk('public')
                ->visibility('public')
                ->directory('payment-receipts/'.($this->order()?->id ?? 0))
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(8192)
                ->openable()
                ->downloadable()
                ->helperText('Images or PDF, up to 8 MB each · up to 10 files.'),
            Forms\Components\Textarea::make('remarks')
                ->label('Note')
                ->rows(2)
                ->maxLength(1000),
        ];
    }

    /** @return array{0: ?PaymentSubmission, 1: ?Payment} the payment entry (submission or payment without one) on this order */
    protected function paymentRecord(array $arguments): array
    {
        $order = $this->order();
        $id = (int) ($arguments['id'] ?? 0);

        if (! $order || $id <= 0) {
            return [null, null];
        }

        if (($arguments['kind'] ?? 'submission') === 'payment') {
            $payment = Payment::query()->where('quotation_id', $order->id)->find($id);

            return [$payment?->submission, $payment];
        }

        $submission = PaymentSubmission::query()->where('quotation_id', $order->id)->find($id);

        return [$submission, $submission?->payment];
    }

    protected function paymentTotalsLine(): ?string
    {
        $order = $this->order();

        return $order
            // live sum of completed payments (also those recorded on delivery / from the CSN)
            ? sprintf('%s · Total RM %s · Paid RM %s · Outstanding RM %s', $order->number, number_format((float) $order->total_amount, 2), number_format(RefreshOrderPaidAmount::livePaid($order), 2), number_format(RefreshOrderPaidAmount::liveOutstanding($order), 2))
            : null;
    }

    protected function addPaymentNote(): HtmlString
    {
        $order = $this->order();
        $warnings = [];

        if ($order && RefreshOrderPaidAmount::liveOutstanding($order) <= 0.004) {
            $warnings[] = 'This order is already fully paid: the amount is recorded as an overpayment and a Refund Note is issued when it is approved.';
        }

        if ($order && $order->billingStatus() === BillingStatus::Generated) {
            $warnings[] = 'Billing is already issued: no new Cash Bill / invoice is issued for this payment automatically.';
        }

        $approval = EditRecordedPayment::allows(auth()->user())
            ? 'Approved straight away · Cash and Pay at Counter payments are verified and need a second user to approve.'
            : 'Finance, Counter, Branch Manager or HQ Admin must approve it before it counts as paid.';

        return new HtmlString(
            ($warnings !== [] ? '<div class="ow-modal-warning" role="alert">'.collect($warnings)->map(fn (string $w) => e($w))->implode('<br>').'</div>' : '')
            .'<div class="ow-modal-note">'.e($approval).'</div>'
        );
    }

    public function verifyPayment(int $submissionId): void
    {
        if ($this->refuseOnOldVersion()) {
            return;
        }

        $this->reviewPayment($submissionId, 'verify');
    }

    public function approvePayment(int $submissionId): void
    {
        if ($this->refuseOnOldVersion()) {
            return;
        }

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
                        AcceptQuotation::CHANNEL_PORTAL => 'Customer portal',
                    ])
                    ->default(AcceptQuotation::CHANNEL_WHATSAPP)
                    ->required(),
                Forms\Components\TextInput::make('confirmed_by_name')->label('Confirmed by (customer contact)'),
                Forms\Components\Textarea::make('consent_evidence')->label('Evidence / remarks')->placeholder('e.g. WhatsApp confirmation received 1 Oct 10:15 from +60 12-345 6789'),
            ])
            ->action(function (array $data): void {
                try {
                    // the offer log rolls back when the accept is refused (e.g. a product without a price)
                    $result = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                        $by = filled($data['confirmed_by_name'] ?? null) ? trim((string) $data['confirmed_by_name']) : null;
                        $channel = $data['channel'] === AcceptQuotation::CHANNEL_PORTAL ? 'Customer portal' : ucfirst((string) $data['channel']);
                        $this->logOfferedPrice($this->order(), 'Customer accepted quotation v'.$this->order()->version.' via '.$channel.($by ? ' ('.$by.')' : ''), 'accepted', $channel.($by ? ' · '.$by : ''));

                        return app(AcceptQuotation::class)->execute($this->order(), $data['channel'], $by, auth()->user(), $data['consent_evidence'] ?? null, recordedByStaff: true);
                    });
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

                    if ($data['return_to_edit'] ?? true) {
                        $revision = app(ReviseQuotation::class)->execute($result->fresh(), auth()->user(), 'Customer rejected v'.$order->version.' · '.$category->getLabel());

                        Notification::make()->title('Rejection recorded · version '.$revision->version.' created for re-pricing')->success()->send();
                        $this->redirect(static::urlFor('order', $revision->id).'?tab=pricing', navigate: false);

                        return;
                    }

                    Notification::make()->title('Rejection recorded · order closed')->success()->send();

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
                        $allowed = $order ? $order->allowedOrderTypes() : OrderType::cases();

                        return collect($allowed)
                            ->reject(fn (OrderType $t) => $t === OrderType::Term && ! $order?->customer?->is_credit)
                            ->mapWithKeys(fn (OrderType $t) => [$t->value => $t->getLabel()]);
                    })
                    ->required()
                    ->helperText('The payment term can change until the customer confirms or a payment is recorded.'),
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
            ->label(fn () => $this->order()?->consignmentNotes()->exists() || in_array($this->order()?->orderType(), [OrderType::Cod, OrderType::Term], true) ? 'Create CSN' : 'Generate Cash Bill → CSN')
            ->requiresConfirmation()
            ->modalDescription(fn () => $this->order()?->billingBlockReason() ?? ($this->order()?->billing_error ? 'Last error: '.$this->order()->billing_error : match ($this->order()?->orderType()) {
                OrderType::Term => 'The CSN is created as Pending Lorry Assignment. Generate the invoice later, when ready.',
                OrderType::Cod => 'The CSN is created as Pending Lorry Assignment. The COD invoice is issued once the order is fully paid.',
                default => 'The Cash Bill is issued and the CSN created as Pending Lorry Assignment.',
            }))
            ->action(function (): void {
                try {
                    $result = app(GenerateOrderBilling::class)->execute($this->order(), auth()->user());
                    Notification::make()
                        ->title($result['ok'] ? trim(($result['invoices']->isNotEmpty() ? 'Billing issued: '.$result['invoices']->pluck('number')->implode(', ').' · ' : '').'CSN: '.$result['csns']->pluck('number')->implode(', ')) : 'Billing generation failed')
                        ->body($result['error'])
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->send();
                    $this->refreshDetail();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /** Credit term: Admin generates the invoice whenever ready, once the CSN exists (e.g. after the CSN is returned). */
    public function issueInvoiceAction(): Action
    {
        return Action::make('issueInvoice')
            ->label('Generate invoice')
            ->requiresConfirmation()
            ->modalHeading('Generate the invoice')
            ->modalDescription(function () {
                $csns = $this->order()?->consignmentNotes()->where('status', '!=', 'cancelled')->get() ?? collect();
                $returned = $csns->filter(fn ($csn) => $csn->isOriginalReturned());

                return 'Issues the credit term invoice for RM '.number_format((float) $this->order()?->total_amount, 2).'. '
                    .($csns->isNotEmpty() && $returned->count() === $csns->count()
                        ? 'The CSN is returned.'
                        : 'Note: the CSN ('.$csns->pluck('number')->implode(', ').') is not marked returned yet.');
            })
            ->modalSubmitActionLabel('Generate invoice')
            ->action(function (): void {
                try {
                    $invoice = app(GenerateOrderBilling::class)->issueInvoice($this->order(), auth()->user());
                    Notification::make()->title('Invoice '.$invoice->number.' issued')->success()->send();
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
            ->label('Email document')
            ->modalHeading('Email a document to the customer')
            ->modalDescription('Choose which document to send. The PDF is attached and the order activity records what was sent and to whom.')
            ->modalSubmitActionLabel('Send email')
            ->modalWidth('7xl')
            ->form(function () {
                $options = $this->order() ? SendOrderDocument::options($this->order()) : [];

                // left: what to send and to whom · right: a preview of the chosen PDF
                return [
                    Forms\Components\Grid::make(['default' => 1, 'lg' => 5])->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('document')
                                ->label('Document')
                                ->options($options)
                                ->default(array_key_first($options))
                                ->live()
                                // a new document gets its own suggested subject and body
                                ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                    if ($state && $this->order()) {
                                        $email = SendOrderDocument::defaultEmail($this->order(), $state);
                                        $set('subject', $email['subject']);
                                        $set('body', $email['body']);
                                    }
                                })
                                ->required(),
                            Forms\Components\TextInput::make('to_email')->label('To')->email()->required()->default($this->order()?->customer?->email),
                            Forms\Components\TextInput::make('subject')
                                ->label('Subject')
                                ->required()
                                ->maxLength(200)
                                ->default(fn () => $this->order() && $options ? SendOrderDocument::defaultEmail($this->order(), (string) array_key_first($options))['subject'] : null),
                            Forms\Components\Textarea::make('body')
                                ->label('Message')
                                ->required()
                                ->rows(10)
                                ->default(fn () => $this->order() && $options ? SendOrderDocument::defaultEmail($this->order(), (string) array_key_first($options))['body'] : null)
                                ->helperText('The PDF is attached to this email.'),
                        ])->columnSpan(['default' => 1, 'lg' => 2]),
                        Forms\Components\Placeholder::make('preview')
                            ->label('Preview')
                            ->content(function (Forms\Get $get) {
                                $url = filled($get('document')) ? SendOrderDocument::pdfUrl((string) $get('document')) : null;

                                return new HtmlString($url
                                    ? '<div class="ow-doc-preview"><iframe src="'.e($url).'#toolbar=0&navpanes=0&view=FitH" title="Document preview"></iframe>'
                                        .'<a href="'.e($url).'" target="_blank" rel="noopener" class="ow-link">Open in new tab</a></div>'
                                    : '<div class="ow-doc-preview ow-doc-preview-empty">Choose a document to preview it.</div>');
                            })
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                    ]),
                ];
            })
            ->action(function (array $data): void {
                $order = $this->order();

                if (! $order) {
                    return;
                }

                try {
                    $log = app(SendOrderDocument::class)->execute($order, (string) $data['document'], auth()->user(), $data['to_email'] ?: null, $data['subject'] ?? null, $data['body'] ?? null);
                    $sentLabel = explode(' · ', SendOrderDocument::options($order)[$data['document']] ?? 'Document')[0];
                    $notice = Notification::make()
                        ->title($sentLabel.' emailed to '.$log->recipient_contact)
                        ->body($log->status === 'sent' ? null : 'Delivery status: '.$log->status);
                    ($log->status === 'failed' ? $notice->danger() : $notice->success())->send();
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
}
