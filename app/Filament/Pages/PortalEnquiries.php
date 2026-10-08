<?php

namespace App\Filament\Pages;

use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use App\Support\PortalEnquiryListingData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PortalEnquiries extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Customer Order Review';

    protected static ?int $navigationSort = 12;

    /** Superseded in the sidebar by the Orders workspace (App\Filament\Pages\Orders / OrderDetail); kept routed for old links. */
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.portal-enquiries';

    public ?string $filterSearch = null;

    public ?string $filterStatus = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public ?int $selectedEnquiryId = null;

    public string $rejectReason = '';

    public bool $showRejectForm = false;

    public ?string $assignSalespersonId = null;

    public function mount(): void
    {
        if (! $this->filterDateFrom) {
            $this->filterDateFrom = now()->subDays(7)->format('Y-m-d');
        }

        if (! $this->filterDateTo) {
            $this->filterDateTo = now()->format('Y-m-d');
        }

        if (! $this->selectedEnquiryId) {
            $first = collect($this->getListingData()['rows'])->first();
            $this->selectedEnquiryId = $first['id'] ?? null;
        }
    }

    public function getTitle(): string
    {
        return 'Customer Order Review';
    }

    public function getSubheading(): ?string
    {
        return 'Review and verify transportation requests submitted via the Customer Portal.';
    }

    public function applyFilters(): void
    {
        $first = collect($this->getListingData()['rows'])->first();
        $this->selectedEnquiryId = $first['id'] ?? null;
    }

    public function resetFilters(): void
    {
        $this->filterSearch = null;
        $this->filterStatus = null;
        $this->filterDateFrom = now()->subDays(7)->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
        $this->applyFilters();
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return app(PortalEnquiryListingData::class)->statusFilterOptions();
    }

    /** @return array<string, mixed> */
    public function getListingData(): array
    {
        return app(PortalEnquiryListingData::class)->for([
            'search' => $this->filterSearch,
            'status' => $this->filterStatus,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function getSelectedDetail(): ?array
    {
        if (! $this->selectedEnquiryId) {
            return null;
        }

        $enquiry = $this->findEnquiry($this->selectedEnquiryId);

        if (! $enquiry) {
            return null;
        }

        return app(PortalEnquiryListingData::class)->detail($enquiry);
    }

    public function getDateRangeLabel(): string
    {
        $from = $this->filterDateFrom
            ? Carbon::parse($this->filterDateFrom)->format('d/m/Y')
            : '—';
        $to = $this->filterDateTo
            ? Carbon::parse($this->filterDateTo)->format('d/m/Y')
            : '—';

        return $from.' - '.$to;
    }

    /*
    |--------------------------------------------------------------------------
    | Section A: one salesperson per enquiry + temporary edit lock (2s heartbeat)
    |--------------------------------------------------------------------------
    */

    public function openDetail(int $enquiryId): void
    {
        $this->releaseSelectedLock();

        $this->selectedEnquiryId = $enquiryId;
        $this->rejectReason = '';
        $this->showRejectForm = false;
        $this->assignSalespersonId = null;

        $enquiry = $this->findEnquiry($enquiryId);

        if ($enquiry && auth()->user() && ! $enquiry->acquireLock(auth()->user())) {
            Notification::make()
                ->title('Currently being attended')
                ->body(($enquiry->locker?->name ?? 'Another user').' is attending this enquiry. It is read-only until they finish.')
                ->warning()
                ->send();
        }
    }

    /** Called every 2 seconds by the open detail panel (wire:poll). */
    public function heartbeat(): void
    {
        if (! $this->selectedEnquiryId || ! auth()->user()) {
            return;
        }

        $this->findEnquiry($this->selectedEnquiryId)?->heartbeat(auth()->user());
    }

    public function releaseSelectedLock(): void
    {
        if ($this->selectedEnquiryId && auth()->user()) {
            $this->findEnquiry($this->selectedEnquiryId)?->releaseLock(auth()->user());
        }
    }

    /** @return array<int, string> */
    public function salespersonOptions(): array
    {
        return User::query()
            ->role('salesperson')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name.($user->saLocation ? ' · '.$user->saLocation->code : '')])
            ->all();
    }

    public function assignSalesperson(int $enquiryId): void
    {
        $enquiry = $this->findEnquiry($enquiryId);
        $salesperson = filled($this->assignSalespersonId) ? User::query()->find($this->assignSalespersonId) : null;

        if (! $enquiry || ! $salesperson) {
            Notification::make()->title('Select a salesperson first')->warning()->send();

            return;
        }

        try {
            app(AssignEnquirySalesperson::class)->execute($enquiry, $salesperson, auth()->user(), lock: true, source: $enquiry->source);
            Notification::make()->title('Salesperson assigned: '.$salesperson->name)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function approveOrder(int $enquiryId): void
    {
        $enquiry = $this->findEnquiry($enquiryId);

        if (! $enquiry) {
            return;
        }

        if ($enquiry->isLockedByOther(auth()->user())) {
            Notification::make()->title('This enquiry is being attended by '.$enquiry->locker?->name)->warning()->send();

            return;
        }

        if ($enquiry->status === PortalEnquiryStatus::Quoted) {
            Notification::make()
                ->title('Order already approved')
                ->body('A quotation has been linked to this portal order.')
                ->info()
                ->send();

            return;
        }

        $enquiry->update(['status' => PortalEnquiryStatus::InReview, 'attended_by' => $enquiry->attended_by ?? auth()->id(), 'attended_at' => $enquiry->attended_at ?? now()]);

        Notification::make()
            ->title('Order approved for pricing review')
            ->success()
            ->send();
    }

    public function createQuotation(int $enquiryId): void
    {
        $enquiry = $this->findEnquiry($enquiryId);

        if (! $enquiry) {
            Notification::make()
                ->title('Order not found')
                ->warning()
                ->send();

            return;
        }

        if ($enquiry->isLockedByOther(auth()->user())) {
            Notification::make()->title('This enquiry is being attended by '.$enquiry->locker?->name)->warning()->send();

            return;
        }

        // Already priced: open the order in the Create order layout (Edit order) instead of creating every record again
        if (UpdateOrderRecords::recordsQuery($enquiry)->exists()) {
            $this->redirect(EditOrder::urlFor('enquiry', (int) $enquiry->id), navigate: false);

            return;
        }

        // Ownership: the attending salesperson takes the enquiry; otherwise one must be selected.
        if (! $enquiry->salesperson_id) {
            if (auth()->user()?->isSalesperson()) {
                try {
                    $enquiry = app(AssignEnquirySalesperson::class)->execute($enquiry, auth()->user(), auth()->user(), lock: true);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
            } else {
                Notification::make()
                    ->title('Assign a salesperson first')
                    ->body('Every enquiry must be owned by one salesperson before an order can be prepared.')
                    ->warning()
                    ->send();

                return;
            }
        }

        $enquiry->update(['attended_by' => $enquiry->attended_by ?? auth()->id(), 'attended_at' => $enquiry->attended_at ?? now()]);

        // The order records (one per consignor–consignee pair) are created here and priced in the Orders workspace.
        try {
            $orders = app(CreateOrderFromEnquiry::class)->execute($enquiry, auth()->user());
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->releaseSelectedLock();

        Notification::make()
            ->title($orders->count().' order record(s) created under '.$enquiry->orderNumber())
            ->body($orders->pluck('number')->implode(', ').' · enter the charges to continue.')
            ->success()
            ->send();

        $this->redirect(OrderDetail::urlFor('order', (int) $orders->first()->id).'?tab=pricing', navigate: false);
    }

    public function toggleRejectForm(): void
    {
        $this->showRejectForm = ! $this->showRejectForm;
    }

    public function rejectEnquiry(int $enquiryId): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:3|max:1000',
        ]);

        $enquiry = $this->findEnquiry($enquiryId);

        if (! $enquiry) {
            return;
        }

        if ($enquiry->isLockedByOther(auth()->user())) {
            Notification::make()->title('This enquiry is being attended by '.$enquiry->locker?->name)->warning()->send();

            return;
        }

        $payload = $enquiry->payload ?? [];
        $payload['rejection_reason'] = $this->rejectReason;

        $enquiry->update([
            'status' => PortalEnquiryStatus::Rejected,
            'payload' => $payload,
        ]);

        $this->rejectReason = '';
        $this->showRejectForm = false;

        Notification::make()
            ->title('Order rejected')
            ->success()
            ->send();
    }

    public function exportOrders(): StreamedResponse
    {
        $rows = $this->getListingData()['rows'] ?? [];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Portal Order ID', 'Customer', 'Destination', 'Submitted Date', 'Review Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['reference_no'],
                    $row['customer'],
                    $row['destination'],
                    $row['submitted_at'],
                    $row['status_label'],
                ]);
            }

            fclose($handle);
        }, 'customer-order-review-'.now()->format('Ymd-His').'.csv');
    }

    protected function findEnquiry(int $enquiryId): ?PortalEnquiry
    {
        $query = PortalEnquiry::query()->with(['customer', 'branch', 'user', 'quotation', 'salesperson', 'locker']);

        if ($companyId = \App\Support\CurrentCompany::id()) {
            $query->where(function ($builder) use ($companyId): void {
                $builder->where('company_id', $companyId)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_id', $companyId));
            });
        }

        return $query->find($enquiryId);
    }
}
