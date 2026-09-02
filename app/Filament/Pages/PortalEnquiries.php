<?php

namespace App\Filament\Pages;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\PortalEnquiryStatus;
use App\Filament\Resources\QuotationResource;
use App\Support\PortalEnquiryListingData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalEnquiries extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Customer Order Review';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.portal-enquiries';

    public ?string $filterSearch = null;

    public ?string $filterStatus = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public ?int $selectedEnquiryId = null;

    public string $rejectReason = '';

    public bool $showRejectForm = false;

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

    public function openDetail(int $enquiryId): void
    {
        $this->selectedEnquiryId = $enquiryId;
        $this->rejectReason = '';
        $this->showRejectForm = false;
    }

    public function approveOrder(int $enquiryId): void
    {
        $enquiry = $this->findEnquiry($enquiryId);

        if (! $enquiry) {
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

        $enquiry->update(['status' => PortalEnquiryStatus::InReview]);

        Notification::make()
            ->title('Order approved for pricing review')
            ->success()
            ->send();
    }

    public function createQuotation(int $enquiryId): void
    {
        if (! $this->findEnquiry($enquiryId)) {
            Notification::make()
                ->title('Order not found')
                ->warning()
                ->send();

            return;
        }

        session(['portal_enquiry_id' => $enquiryId]);

        $this->redirect(QuotationResource::getUrl('create'), navigate: false);
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

    private function findEnquiry(int $enquiryId): ?PortalEnquiry
    {
        $query = PortalEnquiry::query()->with(['customer', 'branch', 'user', 'quotation']);

        if ($companyId = \App\Support\CurrentCompany::id()) {
            $query->where(function ($builder) use ($companyId): void {
                $builder->where('company_id', $companyId)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_id', $companyId));
            });
        }

        return $query->find($enquiryId);
    }
}
