<?php

namespace App\Filament\Pages;

use App\Domains\Quotation\Actions\DecideCreditApproval;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Support\CreditApprovalListingData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CreditCustomerApproval extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Credit Customer Approval';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.credit-customer-approval';

    public ?string $filterSearch = null;

    public ?string $filterStatus = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public ?int $selectedRequestId = null;

    public string $rejectReason = '';

    public string $infoRequestNote = '';

    public ?float $approvedLimit = null;

    public bool $showRejectForm = false;

    public bool $showInfoForm = false;

    public bool $showFilterPanel = false;

    public function mount(): void
    {
        if (! $this->filterDateFrom) {
            $this->filterDateFrom = now()->subDays(30)->format('Y-m-d');
        }

        if (! $this->filterDateTo) {
            $this->filterDateTo = now()->format('Y-m-d');
        }

        if (! $this->selectedRequestId) {
            $first = collect($this->getListingData()['rows'])->first();
            if ($first) {
                $this->openDetail($first['id']);
            }
        }
    }

    public function getTitle(): string
    {
        return 'Credit Customer Approval';
    }

    public function getSubheading(): ?string
    {
        return 'Review credit limit applications and approve or reject customer credit requests.';
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return app(CreditApprovalListingData::class)->statusFilterOptions();
    }

    /** @return array<string, mixed> */
    public function getListingData(): array
    {
        return app(CreditApprovalListingData::class)->for([
            'search' => $this->filterSearch,
            'status' => $this->filterStatus,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function getSelectedDetail(): ?array
    {
        if (! $this->selectedRequestId) {
            return null;
        }

        $request = $this->findRequest($this->selectedRequestId);

        if (! $request) {
            return null;
        }

        $detail = app(CreditApprovalListingData::class)->detail($request);

        return $detail;
    }

    public function openDetail(int $requestId): void
    {
        $this->selectedRequestId = $requestId;
        $this->rejectReason = '';
        $this->infoRequestNote = '';
        $this->showRejectForm = false;
        $this->showInfoForm = false;

        $request = $this->findRequest($requestId);
        $this->approvedLimit = $request
            ? (float) ($request->requested_amount ?? 0)
            : null;
    }

    public function toggleFilterPanel(): void
    {
        $this->showFilterPanel = ! $this->showFilterPanel;
    }

    public function resetFilters(): void
    {
        $this->filterSearch = null;
        $this->filterStatus = null;
        $this->filterDateFrom = now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');

        $first = collect($this->getListingData()['rows'])->first();
        if ($first) {
            $this->openDetail($first['id']);
        } else {
            $this->selectedRequestId = null;
        }
    }

    public function toggleRejectForm(): void
    {
        $this->showRejectForm = ! $this->showRejectForm;
        $this->showInfoForm = false;
    }

    public function toggleInfoForm(): void
    {
        $this->showInfoForm = ! $this->showInfoForm;
        $this->showRejectForm = false;
    }

    public function approveWithLimit(int $requestId): void
    {
        $this->validate([
            'approvedLimit' => 'required|numeric|min:0',
        ]);

        $request = $this->findRequest($requestId);

        if (! $request) {
            return;
        }

        try {
            $remarks = sprintf('Approved with limit MYR %s', number_format((float) $this->approvedLimit, 2));
            app(DecideCreditApproval::class)->execute($request, auth()->user(), true, $remarks);

            Notification::make()
                ->title('Credit approved')
                ->body($remarks)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Unable to approve')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function requestInfo(int $requestId): void
    {
        $this->validate([
            'infoRequestNote' => 'required|string|min:3|max:1000',
        ]);

        $request = $this->findRequest($requestId);

        if (! $request) {
            return;
        }

        $payload = $request->trigger_details ?? [];
        $payload['assessment_notes'] = $this->infoRequestNote;
        $payload['info_requested_at'] = now()->toIso8601String();

        $request->update([
            'trigger_details' => $payload,
            'remarks' => $this->infoRequestNote,
        ]);

        $this->infoRequestNote = '';
        $this->showInfoForm = false;

        Notification::make()
            ->title('Information request recorded')
            ->body('Assessment notes updated for this application.')
            ->success()
            ->send();
    }

    public function rejectApplication(int $requestId): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:3|max:1000',
        ]);

        $request = $this->findRequest($requestId);

        if (! $request) {
            return;
        }

        try {
            app(DecideCreditApproval::class)->execute($request, auth()->user(), false, $this->rejectReason);

            $this->rejectReason = '';
            $this->showRejectForm = false;

            Notification::make()
                ->title('Application rejected')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Unable to reject')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportApplications(): StreamedResponse
    {
        $rows = $this->getListingData()['rows'] ?? [];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Customer Name', 'Registration No', 'Limit Requested (MYR)', 'Branch', 'App Date', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['customer_name'],
                    $row['registration_no'],
                    $row['requested_limit'],
                    $row['branch'],
                    $row['app_date'],
                    $row['status_label'],
                ]);
            }

            fclose($handle);
        }, 'credit-customer-approval-'.now()->format('Ymd-His').'.csv');
    }

    private function findRequest(int $requestId): ?CreditApprovalRequest
    {
        $query = CreditApprovalRequest::query()
            ->with(['customer', 'branch', 'quotation', 'requester', 'approver']);

        if ($companyId = \App\Support\CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->find($requestId);
    }
}
