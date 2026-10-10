<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Customer;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource;
use App\Support\CurrentCompany;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    public ?int $customerId = null;

    /** Scanned / typed CSN number (QR code on the CSN). */
    public string $scan = '';

    /** @var list<int> */
    public array $selectedCsnIds = [];

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
            ['key' => 'counter', 'label' => 'Branch-Configured Method'],
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

        return Customer::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('company_name')
            ->get(['id', 'company_name'])
            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->company_name.' ('.$counts[$c->id].' unpaid)'])
            ->all();
    }

    /** A different customer: only CSNs of the new customer stay ticked. */
    public function updatedCustomerId(): void
    {
        $this->customerId = $this->customerId ?: null;
        $keep = $this->customerId ? $this->unpaidCashBillQuery()->where('customer_id', $this->customerId)->pluck('id')->all() : [];
        $this->selectedCsnIds = array_values(array_intersect($this->selectedCsnIds, $keep));
        $this->lastReceiptNumber = null;
    }

    /** Every unpaid Cash Bill CSN of the chosen customer, oldest first. @return Collection<int, ConsignmentNote> */
    #[Computed]
    public function customerCsns(): Collection
    {
        if (! $this->customerId) {
            return collect();
        }

        return $this->unpaidCashBillQuery()
            ->with(['sourceBranch'])
            ->where('customer_id', $this->customerId)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
    }

    public function toggleCsn(int $csnId): void
    {
        in_array($csnId, $this->selectedCsnIds, true) ? $this->removeCsn($csnId) : $this->addCsn($csnId);
    }

    public function toggleAll(): void
    {
        $ids = $this->customerCsns->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->selectedCsnIds = count(array_diff($ids, $this->selectedCsnIds)) === 0 ? [] : $ids;
        $this->lastReceiptNumber = null;
    }

    /** Scanning a CSN's QR code (or typing its number) ticks it; picks its customer when none is chosen. */
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

        if ($this->customerId && (int) $csn->customer_id !== (int) $this->customerId) {
            $this->customerId = (int) $csn->customer_id;
            $this->selectedCsnIds = [];
            Notification::make()->title('Switched customer')->body($csn->number.' belongs to '.($csn->customer_name ?: 'another customer').'; the list now shows that customer.')->info()->send();
        }

        $this->customerId = (int) $csn->customer_id ?: null;
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
        if ($this->selectedCsnIds === []) {
            Notification::make()->title('Select at least one CSN')->warning()->send();

            return;
        }

        $this->validate([
            'slips' => ['array', 'max:10'],
            'slips.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [], ['slips.*' => 'payment slip']);

        $csns = ConsignmentNote::query()
            ->whereIn('id', $this->selectedCsnIds)
            ->get();

        $total = (float) $csns->sum('total_amount');
        $received = (float) $this->amountReceived;

        if ($received + 0.0001 < $total) {
            Notification::make()
                ->title('Insufficient amount')
                ->body('Total due is RM '.number_format($total, 2))
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

        foreach ($csns as $csn) {
            $payment = app(RecordPayment::class)->execute([
                'source_branch_id' => $csn->source_branch_id,
                'consignment_note_id' => $csn->id,
                'customer_id' => $csn->customer_id,
                'amount' => $csn->total_amount,
                'method' => $this->method,
                'slip_path' => $slipPaths[0] ?? null,
                'slip_paths' => $slipPaths !== [] ? $slipPaths : null,
            ], auth()->user());

            if ($payment->receipt?->number) {
                $receiptNumbers[] = $payment->receipt->number;
            }
        }

        $change = round($received - $total, 2);
        $this->lastReceiptNumber = $receiptNumbers[0] ?? null;

        Notification::make()
            ->title('Collected '.count($this->selectedCsnIds).' Cash Bill(s)')
            ->body('Change: RM '.number_format($change, 2).($slipPaths ? ' · '.count($slipPaths).' slip(s) attached' : ''))
            ->success()
            ->send();

        $this->selectedCsnIds = [];
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

        return ConsignmentNote::query()
            ->with(['customer', 'sourceBranch'])
            ->whereIn('id', $this->selectedCsnIds)
            ->get()
            ->sortBy(fn (ConsignmentNote $csn) => array_search($csn->id, $this->selectedCsnIds, true));
    }

    #[Computed]
    public function totalDue(): float
    {
        return (float) $this->selectedCsns->sum('total_amount');
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
