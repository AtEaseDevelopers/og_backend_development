<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Billing\Actions\RefreshOrderPaidAmount;
use App\Domains\Billing\Actions\ReviewPaymentSubmission;
use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Customer;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource;
use App\Support\CurrentCompany;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Support\PaymentAllocation;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

/**
 * Cash Bill payment at the counter: Billing → "Cash Bill Payment (Counter Use)" (menu item since 10 Oct 2026; the
 * "Create Cash Bill Payment" button on Payments & Receipts was removed).
 * Pick the customer to list all their unpaid Cash Bill CSNs and tick the ones being paid, or scan a CSN's QR
 * code (handheld scanner or typed number) to tick it. Payment slips / receipts can be uploaded; every payment
 * recorded here shows in the payment listing.
 */
class CashBillCalculator extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Cash Bill Payment (Counter Use)';

    // right after Payments & Receipts (20): with the same sort as Invoices (21), a page is listed before a resource
    protected static ?int $navigationSort = 21;

    protected static string $view = 'filament.pages.cash-bill-calculator';

    /** The customer whose unpaid Cash Bill CSNs are listed to pick from. */
    public ?int $customerId = null;

    /** Scanned / typed CSN number (QR code on the CSN): added straight to the payment list. */
    public string $scan = '';

    /** CSNs ticked in the customer's list, added with "Add". @var list<int> */
    public array $pickedCsnIds = [];

    /** Cash orders in Pending payment (no CSN yet) ticked in the customer's list. @var list<int> */
    public array $pickedOrderIds = [];

    /** The payment list: the CSNs to pay, of any customers (user, 20 Oct 2026). @var list<int> */
    public array $selectedCsnIds = [];

    /** …and the Pending payment Cash orders: paid here, their Cash Bill and CSN are created once fully paid. @var list<int> */
    public array $selectedOrderIds = [];

    /**
     * Received less than the list's total (user, 21 Oct 2026): how much goes to each item ("csn_12" / "order_34" →
     * RM), entered by the user; filled in list order until changed. @var array<string, string>
     */
    public array $allocations = [];

    public string $method = 'cash';

    public string $amountReceived = '0.00';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> payment slips / receipts */
    public array $slips = [];

    public ?string $lastReceiptNumber = null;

    public function getTitle(): string
    {
        return 'Cash Bill Payment (Counter Use)';
    }

    public function getSubheading(): ?string
    {
        return 'Choose the customer and tick the Cash Bill CSNs being paid (or scan their QR codes), record the payment and print the receipt.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToPayments')
                ->label('Back to Payment Listing')
                ->color('gray')
                ->url(fn (): string => PaymentResource::getUrl()),
            Action::make('printReceipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->disabled(fn (): bool => blank($this->lastReceiptNumber))
                ->action(fn () => $this->dispatch('print-cash-bill-receipt')),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function paymentMethods(): array
    {
        return [
            ['key' => 'cash', 'label' => 'Cash'],
            ['key' => 'ewallet', 'label' => 'eWallet'],
            ['key' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['key' => 'online', 'label' => 'Online Payment'],
            ['key' => 'counter', 'label' => 'Company-Configured Method'],
        ];
    }

    public function mount(): void
    {
        $this->amountReceived = '0.00';
    }

    /** Customers with at least one unpaid Cash Bill CSN, with the count. @return array<int, string> */
    #[Computed]
    public function customerOptions(): array
    {
        $counts = $this->unpaidCashBillQuery()
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, count(*) as n')
            ->groupBy('customer_id')
            ->pluck('n', 'customer_id');

        // …and the customers' Cash orders waiting for payment (no CSN yet)
        foreach ($this->pendingCashOrders()->countBy('customer_id') as $customerId => $n) {
            $counts[$customerId] = ($counts[$customerId] ?? 0) + $n;
        }

        return Customer::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('company_name')
            ->get(['id', 'company_name'])
            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->company_name.' ('.$counts[$c->id].' unpaid)'])
            ->all();
    }

    /** Another customer: its CSNs are listed to pick from; the payment list keeps what was added (any customers). */
    /** Another amount received: the split over the list starts again (in list order). */
    public function updatedAmountReceived(): void
    {
        $this->allocations = [];
    }

    /** The split shown in the Paying column: the amounts entered, the missing ones filled in list order. */
    public function dehydrate(): void
    {
        $this->allocations = PaymentAllocation::amounts($this->itemDues(), round((float) $this->amountReceived, 2), $this->allocations);
    }

    /**
     * The payment list's items and their dues, in list order (CSNs, then orders).
     *
     * @return array<string, float>
     */
    public function itemDues(): array
    {
        return $this->selectedCsns->mapWithKeys(fn (ConsignmentNote $csn) => ['csn_'.$csn->id => $this->dueFor($csn)])
            ->merge($this->selectedOrders->mapWithKeys(fn (Quotation $order) => ['order_'.$order->id => $this->dueForOrder($order)]))
            ->all();
    }

    /** How the amount received is spread over the list (PaymentAllocation::plan). */
    #[Computed]
    public function allocationPlan(): array
    {
        return PaymentAllocation::plan($this->itemDues(), round((float) $this->amountReceived, 2), $this->allocations);
    }

    public function updatedCustomerId(): void
    {
        $this->customerId = $this->customerId ?: null;
        $this->pickedCsnIds = [];
        $this->pickedOrderIds = [];
        $this->lastReceiptNumber = null;
    }

    /**
     * Confirmed Cash orders still owed (Pending payment, no CSN yet) of this company, with what is still due.
     *
     * @return Collection<int, Quotation>
     */
    protected function pendingCashOrders(?int $customerId = null): Collection
    {
        return Quotation::query()
            ->where('status', QuotationStatus::Confirmed->value)
            ->where('order_type', OrderType::Cash->value)
            ->whereDoesntHave('consignmentNotes')
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->whereNotNull('customer_id')
            ->with(['customer:id,company_name', 'branch'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Quotation $order) => $order->isLatestVersion() && $this->dueForOrder($order) > 0.009)
            ->values();
    }

    /** Still due on an order: its total less its completed payments. */
    public function dueForOrder(Quotation $order): float
    {
        return RefreshOrderPaidAmount::liveOutstanding($order);
    }

    /** The chosen customer's Pending payment Cash orders. @return Collection<int, Quotation> */
    #[Computed]
    public function customerOrders(): Collection
    {
        return $this->customerId ? $this->pendingCashOrders($this->customerId) : collect();
    }

    /** @return Collection<int, Quotation> the orders of the payment list */
    #[Computed]
    public function selectedOrders(): Collection
    {
        return $this->selectedOrderIds === []
            ? collect()
            : Quotation::query()->with(['customer:id,company_name', 'branch'])->whereIn('id', $this->selectedOrderIds)->get()
                ->sortBy(fn (Quotation $order) => array_search($order->id, $this->selectedOrderIds, true))->values();
    }

    public function togglePickOrder(int $orderId): void
    {
        if (in_array($orderId, $this->selectedOrderIds, true)) {
            return;
        }

        $this->pickedOrderIds = in_array($orderId, $this->pickedOrderIds, true)
            ? array_values(array_filter($this->pickedOrderIds, fn (int $id) => $id !== $orderId))
            : [...$this->pickedOrderIds, $orderId];
    }

    public function removeOrderItem(int $orderId): void
    {
        $this->selectedOrderIds = array_values(array_filter($this->selectedOrderIds, fn (int $id) => $id !== $orderId));
        $this->lastReceiptNumber = null;
    }

    /** Every unpaid Cash Bill CSN of the chosen customer, oldest first. @return Collection<int, ConsignmentNote> */
    #[Computed]
    public function customerCsns(): Collection
    {
        if (! $this->customerId) {
            return collect();
        }

        return $this->withPaid($this->unpaidCashBillQuery())
            ->with(['sourceBranch'])
            ->where('customer_id', $this->customerId)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
    }

    /** @param  Builder<ConsignmentNote>  $query */
    private function withPaid(Builder $query): Builder
    {
        return $query->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'completed')], 'amount');
    }

    /** Still due on a CSN: its total less what was already paid (a CSN left with a balance before). */
    public function dueFor(ConsignmentNote $csn): float
    {
        return max(0, round((float) $csn->total_amount - (float) ($csn->paid_total ?? 0), 2));
    }

    /** Tick / untick a CSN of the customer's list (not yet in the payment list). */
    public function togglePick(int $csnId): void
    {
        if (in_array($csnId, $this->selectedCsnIds, true)) {
            return;
        }

        $this->pickedCsnIds = in_array($csnId, $this->pickedCsnIds, true)
            ? array_values(array_filter($this->pickedCsnIds, fn (int $id) => $id !== $csnId))
            : [...$this->pickedCsnIds, $csnId];
    }

    public function togglePickAll(): void
    {
        $open = $this->customerCsns->pluck('id')->map(fn ($id) => (int) $id)->reject(fn (int $id) => in_array($id, $this->selectedCsnIds, true))->values()->all();
        $openOrders = $this->customerOrders->pluck('id')->map(fn ($id) => (int) $id)->reject(fn (int $id) => in_array($id, $this->selectedOrderIds, true))->values()->all();
        $all = ($open !== [] || $openOrders !== []) && count(array_diff($open, $this->pickedCsnIds)) === 0 && count(array_diff($openOrders, $this->pickedOrderIds)) === 0;
        $this->pickedCsnIds = $all ? [] : $open;
        $this->pickedOrderIds = $all ? [] : $openOrders;
    }

    /** "Add": the ticked CSNs go into the payment list (which may hold CSNs of different customers). */
    public function addPicked(): void
    {
        if ($this->pickedCsnIds === [] && $this->pickedOrderIds === []) {
            Notification::make()->title('Tick the CSNs or orders to add')->warning()->send();

            return;
        }

        foreach ($this->pickedCsnIds as $id) {
            $this->addCsn((int) $id);
        }

        $open = $this->pendingCashOrders()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($this->pickedOrderIds as $id) {
            if (in_array((int) $id, $open, true) && ! in_array((int) $id, $this->selectedOrderIds, true)) {
                $this->selectedOrderIds[] = (int) $id;
            }
        }

        $this->pickedCsnIds = [];
        $this->pickedOrderIds = [];
        $this->lastReceiptNumber = null;
    }

    /** Scanning a CSN's QR code (or typing its number) adds it straight to the payment list (any customer). */
    public function scanCsn(?string $value = null): void
    {
        $term = trim((string) ($value ?? $this->scan));
        $this->scan = '';

        if ($term === '') {
            return;
        }

        $csn = $this->cashBillCsnQuery()->where(fn (Builder $q) => $q->where('number', $term)->orWhere('qr_token', $term))->first();

        if (! $csn) {
            Notification::make()->title('CSN not found')->body('No Cash Bill CSN "'.$term.'" in this company.')->warning()->send();

            return;
        }

        if (in_array($csn->payment_status, [PaymentStatus::Paid, PaymentStatus::CodCollected], true)) {
            Notification::make()->title('Already paid')->body($csn->number.' has already been paid.')->warning()->send();

            return;
        }

        if (in_array((int) $csn->id, $this->selectedCsnIds, true)) {
            Notification::make()->title('Already in the payment list')->body($csn->number)->info()->send();

            return;
        }

        $this->addCsn((int) $csn->id);
    }

    public function addCsn(int $csnId): void
    {
        if (in_array($csnId, $this->selectedCsnIds, true)) {
            return;
        }

        if (! $this->unpaidCashBillQuery()->whereKey($csnId)->exists()) {
            Notification::make()->title('CSN unavailable')->warning()->send();

            return;
        }

        $this->selectedCsnIds[] = $csnId;
        $this->pickedCsnIds = array_values(array_filter($this->pickedCsnIds, fn (int $id) => $id !== $csnId));
        $this->lastReceiptNumber = null;
    }

    public function removeCsn(int $csnId): void
    {
        $this->selectedCsnIds = array_values(array_filter(
            $this->selectedCsnIds,
            fn (int $id): bool => $id !== $csnId,
        ));
        $this->lastReceiptNumber = null;
    }

    public function removeSlip(int $index): void
    {
        unset($this->slips[$index]);
        $this->slips = array_values($this->slips);
    }

    public function selectMethod(string $method): void
    {
        $allowed = collect($this->paymentMethods())->pluck('key')->all();

        if (! in_array($method, $allowed, true)) {
            return;
        }

        $this->method = $method;
    }

    public function applyFullPayment(): void
    {
        $this->amountReceived = number_format($this->totalDue, 2, '.', '');
    }

    public function process(): void
    {
        if ($this->selectedCsnIds === [] && $this->selectedOrderIds === []) {
            Notification::make()->title('Add at least one CSN or order to the payment list')->warning()->send();

            return;
        }

        $this->validate([
            'slips' => ['array', 'max:10'],
            'slips.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [], ['slips.*' => 'payment slip']);

        $csns = $this->withPaid(ConsignmentNote::query())
            ->whereIn('id', $this->selectedCsnIds)
            ->get();

        $orders = $this->selectedOrders;
        $received = round((float) $this->amountReceived, 2);
        // received less than due: each item takes the amount the user gave it (less than its due = partial)
        $plan = PaymentAllocation::plan($this->itemDues(), $received, $this->allocations);

        if ($received <= 0) {
            Notification::make()->title('Enter the amount received')->danger()->send();

            return;
        }

        if (! $plan['ok']) {
            Notification::make()
                ->title('Split the amount received over the payment list')
                ->body('Received RM '.number_format($received, 2).' of RM '.number_format($plan['total'], 2).'. Enter how much goes to each item (Paying column): together RM '.number_format($received, 2).', each at most its due'.(abs($plan['left']) >= 0.005 ? ' · RM '.number_format($plan['left'], 2).' '.($plan['left'] > 0 ? 'not given to an item yet' : 'too much') : '').'.')
                ->danger()
                ->send();

            return;
        }

        // the same slips belong to every CSN paid in this transaction
        $slipPaths = collect($this->slips)
            ->map(fn ($file) => $file->store('payment-receipts/cash-bill/'.now()->format('Y-m'), 'public'))
            ->values()
            ->all();

        $receiptNumbers = [];

        $partial = [];

        foreach ($csns as $csn) {
            $due = $this->dueFor($csn);
            $amount = round((float) ($plan['amounts']['csn_'.$csn->id] ?? 0), 2);

            if ($due <= 0 || $amount <= 0) {
                continue;
            }

            if ($amount < $due - 0.004) {
                $partial[] = $csn->number.' RM '.number_format($amount, 2).' of RM '.number_format($due, 2);
            }

            $payment = app(RecordPayment::class)->execute([
                'source_branch_id' => $csn->source_branch_id,
                'consignment_note_id' => $csn->id,
                'customer_id' => $csn->customer_id,
                'amount' => $amount,
                'expected_amount' => $due,
                'shortage_amount' => $amount < $due - 0.004 ? round($due - $amount, 2) : null,
                'method' => $this->method,
                'slip_path' => $slipPaths[0] ?? null,
                'slip_paths' => $slipPaths !== [] ? $slipPaths : null,
            ], auth()->user());

            if ($payment->receipt?->number) {
                $receiptNumbers[] = $payment->receipt->number;
            }
        }

        // Pending payment Cash orders: paid like Add payment on the order (fully paid → Cash Bill + CSN)
        $orderLines = [];

        foreach ($orders as $order) {
            $due = $this->dueForOrder($order);
            $amount = round((float) ($plan['amounts']['order_'.$order->id] ?? 0), 2);
            $keepsBalance = $amount < $due - 0.004;

            if ($due <= 0 || $amount <= 0) {
                continue;
            }

            if ($keepsBalance) {
                $partial[] = $order->number.' RM '.number_format($amount, 2).' of RM '.number_format($due, 2);
            }

            try {
                $submission = app(SubmitPaymentEvidence::class)->execute($order, [
                    'amount' => $amount,
                    'method' => $this->method,
                    'payment_date' => now()->toDateString(),
                    'reference' => null,
                    'receipt_path' => $slipPaths[0] ?? null,
                    'receipt_paths' => $slipPaths !== [] ? $slipPaths : null,
                    'remarks' => ($keepsBalance ? 'Partial payment.' : 'Full payment.').' Cash Bill Payment (Counter Use).',
                ], auth()->user(), 'admin');

                $review = app(ReviewPaymentSubmission::class);

                if ($submission->requiresTwoApprovals()) {
                    $review->verify($submission, auth()->user());
                    $orderLines[] = $order->number.': paid, a second user approves it'.($keepsBalance ? '' : ' to create the CSN');
                } else {
                    $result = $review->approve($submission, auth()->user());
                    $orderLines[] = $order->number.': paid'.(($result['billing']['ok'] ?? null) === true ? ' · Cash Bill issued, CSN created' : '');
                }
            } catch (\Throwable $e) {
                $orderLines[] = $order->number.': '.$e->getMessage();
            }
        }

        $this->lastReceiptNumber = $receiptNumbers[0] ?? null;

        Notification::make()
            ->title('Collected '.(count($this->selectedCsnIds) + count($this->selectedOrderIds)).' item(s)')
            ->body(($partial !== []
                    ? 'Partial payment: '.implode(', ', $partial).' (the rest is paid next time)'
                    : 'Change: RM '.number_format($plan['change'], 2))
                .($slipPaths ? ' · '.count($slipPaths).' slip(s) attached' : '')
                .($orderLines !== [] ? "\n".implode("\n", $orderLines) : ''))
            ->success()
            ->persistent()
            ->send();

        $this->selectedCsnIds = [];
        $this->selectedOrderIds = [];
        $this->allocations = [];
        $this->amountReceived = '0.00';
        $this->slips = [];
        $this->scan = '';
    }

    #[Computed]
    public function selectedCsns(): Collection
    {
        if ($this->selectedCsnIds === []) {
            return collect();
        }

        return $this->withPaid(ConsignmentNote::query())
            ->with(['customer', 'sourceBranch'])
            ->whereIn('id', $this->selectedCsnIds)
            ->get()
            ->sortBy(fn (ConsignmentNote $csn) => array_search($csn->id, $this->selectedCsnIds, true));
    }

    #[Computed]
    public function totalDue(): float
    {
        return round((float) $this->selectedCsns->sum(fn (ConsignmentNote $csn) => $this->dueFor($csn))
            + (float) $this->selectedOrders->sum(fn (Quotation $order) => $this->dueForOrder($order)), 2);
    }

    #[Computed]
    public function receivedAmount(): float
    {
        return (float) $this->amountReceived;
    }

    #[Computed]
    public function outstandingAmount(): float
    {
        return max(0, round($this->totalDue - $this->receivedAmount, 2));
    }

    #[Computed]
    public function changeAmount(): float
    {
        return max(0, round($this->receivedAmount - $this->totalDue, 2));
    }

    #[Computed]
    public function counterDateLabel(): string
    {
        return now()->format('d/m/Y');
    }

    #[Computed]
    public function branchViewLabel(): string
    {
        $branch = CurrentCompany::branch();

        return $branch ? strtoupper($branch->code).' View' : 'HQ View';
    }

    public function paymentStatusLabel(ConsignmentNote $csn): string
    {
        $status = $csn->payment_status instanceof PaymentStatus
            ? $csn->payment_status
            : PaymentStatus::tryFrom((string) $csn->payment_status);

        if ($status === PaymentStatus::Unpaid || $status === PaymentStatus::Partial) {
            return 'OUTSTANDING';
        }

        return strtoupper($status?->getLabel() ?? 'OUTSTANDING');
    }

    /** @return Builder<ConsignmentNote> */
    private function cashBillCsnQuery(): Builder
    {
        $query = ConsignmentNote::query()
            ->where('billing_type', CsnBillingType::CashBill)
            ->where('status', '!=', CsnStatus::Cancelled);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /** @return Builder<ConsignmentNote> */
    private function unpaidCashBillQuery(): Builder
    {
        return $this->cashBillCsnQuery()
            ->whereNotIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::CodCollected->value]);
    }
}
