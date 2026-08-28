<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
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

class CashBillCalculator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Cash Bill Calculator';

    protected static ?int $navigationSort = 19;

    protected static string $view = 'filament.pages.cash-bill-calculator';

    public string $search = '';

    /** @var list<int> */
    public array $selectedCsnIds = [];

    public string $method = 'cash';

    public string $amountReceived = '0.00';

    public ?string $lastReceiptNumber = null;

    public function getTitle(): string
    {
        return 'Create Cash Bill';
    }

    public function getSubheading(): ?string
    {
        return 'Select Cash Bill CSNs, record payment and review the generated receipt.';
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

    public function updatedSearch(): void
    {
        if (blank($this->search)) {
            return;
        }

        $match = $this->csnSearchResults->first();

        if ($match && count($this->selectedCsnIds) === 0) {
            return;
        }
    }

    public function addFromSearch(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $match = $this->csnSearchResults->first();

        if (! $match) {
            $this->notifySearchMiss($term);

            return;
        }

        $this->addCsn((int) $match->id);
        $this->search = '';
    }

    protected function notifySearchMiss(string $term): void
    {
        $csn = $this->findCashBillCsnByTerm($term);

        if (! $csn) {
            Notification::make()
                ->title('CSN not found')
                ->body('No Cash Bill CSN matches "'.$term.'" in this company.')
                ->warning()
                ->send();

            return;
        }

        if (in_array($csn->payment_status, [PaymentStatus::Paid, PaymentStatus::CodCollected], true)) {
            Notification::make()
                ->title('Already paid')
                ->body($csn->number.' has already been collected. Only outstanding Cash Bill CSNs can be added.')
                ->warning()
                ->send();

            return;
        }

        if (in_array($csn->id, $this->selectedCsnIds, true)) {
            Notification::make()
                ->title('Already selected')
                ->body($csn->number.' is already in the payment list.')
                ->info()
                ->send();

            return;
        }

        Notification::make()
            ->title('CSN unavailable')
            ->body('No unpaid Cash Bill CSN matches "'.$term.'".')
            ->warning()
            ->send();
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

        $csns = ConsignmentNote::query()
            ->whereIn('id', $this->selectedCsnIds)
            ->get();

        $total = (float) $csns->sum('total_amount');
        $received = (float) $this->amountReceived;

        if ($received + 0.0001 < $total) {
            Notification::make()
                ->title('Insufficient amount')
                ->body('Total due is MYR '.number_format($total, 2))
                ->danger()
                ->send();

            return;
        }

        $receiptNumbers = [];

        foreach ($csns as $csn) {
            $payment = app(RecordPayment::class)->execute([
                'source_branch_id' => $csn->source_branch_id,
                'consignment_note_id' => $csn->id,
                'customer_id' => $csn->customer_id,
                'amount' => $csn->total_amount,
                'method' => $this->method,
            ], auth()->user());

            if ($payment->receipt?->number) {
                $receiptNumbers[] = $payment->receipt->number;
            }
        }

        $change = round($received - $total, 2);
        $this->lastReceiptNumber = $receiptNumbers[0] ?? null;

        Notification::make()
            ->title('Collected '.count($this->selectedCsnIds).' Cash Bill(s)')
            ->body('Change: MYR '.number_format($change, 2))
            ->success()
            ->send();

        $this->selectedCsnIds = [];
        $this->amountReceived = '0.00';
        $this->search = '';
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

    /** @return Collection<int, ConsignmentNote> */
    #[Computed]
    public function csnSearchResults(): Collection
    {
        $term = trim($this->search);

        if ($term === '') {
            return collect();
        }

        return $this->unpaidCashBillQuery()
            ->where(function ($query) use ($term) {
                $query->where('number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%');
            })
            ->when($this->selectedCsnIds !== [], fn ($query) => $query->whereNotIn('id', $this->selectedCsnIds))
            ->limit(8)
            ->get();
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

    private function findCashBillCsnByTerm(string $term): ?ConsignmentNote
    {
        return $this->cashBillCsnQuery()
            ->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%');
            })
            ->first();
    }

    /** @return Builder<ConsignmentNote> */
    private function unpaidCashBillQuery(): Builder
    {
        return $this->cashBillCsnQuery()
            ->whereNotIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::CodCollected->value]);
    }
}
