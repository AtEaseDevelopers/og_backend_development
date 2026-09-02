<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\QuotationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class CreditApprovalListingData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $rows = $this->query($filters)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditApprovalRequest $request): array => $this->formatRow($request));

        return [
            'rows' => $rows->values()->all(),
            'count' => $rows->count(),
            'pending_count' => $rows->where('status', 'pending')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(CreditApprovalRequest $request): array
    {
        $request->loadMissing(['customer', 'branch', 'quotation', 'requester', 'approver']);

        $customer = $request->customer;
        $status = $this->statusDisplay($request->status);

        return [
            'id' => $request->id,
            'customer_name' => $customer?->company_name ?? '—',
            'registration_no' => filled($customer?->brn) ? 'SSM: '.$customer->brn : '—',
            'status' => (string) $request->status,
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'requested_limit' => $this->formatLimit((float) ($request->requested_amount ?? 0)),
            'requested_limit_raw' => (float) ($request->requested_amount ?? 0),
            'credit_score' => $customer ? $this->creditScoreGrade($customer) : '—',
            'credit_score_note' => $customer ? $this->creditScoreNote($customer) : null,
            'branch' => $this->branchDisplayName($request->branch),
            'branch_code' => $request->branch?->code ?? '—',
            'app_date' => $request->created_at?->format('d/m/Y') ?? '—',
            'reason' => $request->reason,
            'remarks' => $request->remarks,
            'assessment_notes' => $this->assessmentNotes($request),
            'documents' => $this->documents($request),
            'audit_trail' => $this->auditTrail($request),
            'quotation_number' => $request->quotation?->number,
            'quotation_url' => $request->quotation
                ? QuotationResource::getUrl('view', ['record' => $request->quotation])
                : null,
            'can_approve' => $request->isPending(),
            'can_reject' => $request->isPending(),
            'can_request_info' => $request->isPending(),
        ];
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return [
            '' => 'All statuses',
            'pending' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters): Builder
    {
        $query = CreditApprovalRequest::query()
            ->with(['customer', 'branch', 'quotation']);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereHas('customer', fn (Builder $customer) => $customer
                    ->where('company_name', 'like', '%'.$needle.'%')
                    ->orWhere('brn', 'like', '%'.$needle.'%')
                    ->orWhere('code', 'like', '%'.$needle.'%'));
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', Carbon::parse((string) $filters['date_from'])->toDateString());
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', Carbon::parse((string) $filters['date_to'])->toDateString());
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(CreditApprovalRequest $request): array
    {
        $status = $this->statusDisplay($request->status);

        return [
            'id' => $request->id,
            'customer_name' => $request->customer?->company_name ?? '—',
            'registration_no' => $request->customer?->brn ?? '—',
            'requested_limit' => $this->formatTableAmount((float) ($request->requested_amount ?? 0)),
            'branch' => $this->branchDisplayName($request->branch),
            'app_date' => $request->created_at?->format('d/m/Y') ?? '—',
            'status' => (string) $request->status,
            'status_label' => $status['list_label'],
            'status_color' => $status['color'],
        ];
    }

    /** @return array{label: string, list_label: string, color: string} */
    private function statusDisplay(?string $status): array
    {
        return match ($status) {
            'pending' => ['label' => 'UNDER REVIEW', 'list_label' => 'Under Review', 'color' => 'blue'],
            'approved' => ['label' => 'APPROVED', 'list_label' => 'Approved', 'color' => 'approved'],
            'rejected' => ['label' => 'REJECTED', 'list_label' => 'Rejected', 'color' => 'danger'],
            default => [
                'label' => strtoupper((string) $status),
                'list_label' => ucfirst((string) $status),
                'color' => 'gray',
            ],
        };
    }

    private function branchDisplayName(?\App\Domains\MasterData\Models\Branch $branch): string
    {
        return match ($branch?->code) {
            'KL' => 'Klang Valley',
            'JB' => 'Johor South',
            'KLG' => 'Port Klang',
            'PG' => 'Penang',
            default => $branch?->name ?? '—',
        };
    }

    private function formatTableAmount(float $amount): string
    {
        return number_format($amount, 2);
    }

    private function formatLimit(float $amount): string
    {
        if ($amount >= 1000) {
            $thousands = $amount / 1000;

            if (fmod($thousands, 1.0) === 0.0) {
                return 'MYR '.number_format($thousands, 0).'k';
            }

            return 'MYR '.number_format($thousands, 1).'k';
        }

        return 'MYR '.number_format($amount, 0);
    }

    private function creditScoreGrade(\App\Domains\MasterData\Models\Customer $customer): string
    {
        $limit = (float) $customer->credit_limit;
        $outstanding = $this->outstandingBalance($customer);
        $utilization = $limit > 0 ? ($outstanding / $limit) : 0;

        if ($limit >= 200000 && $utilization < 0.5) {
            return 'A';
        }

        if ($limit >= 100000 && $utilization < 0.6) {
            return 'A-';
        }

        if ($limit >= 50000) {
            return 'B+';
        }

        return 'B';
    }

    private function creditScoreNote(\App\Domains\MasterData\Models\Customer $customer): string
    {
        $limit = (float) $customer->credit_limit;
        $outstanding = $this->outstandingBalance($customer);

        if ($limit <= 0) {
            return 'No existing credit limit on file.';
        }

        return sprintf(
            'Current limit MYR %s · Outstanding MYR %s',
            number_format($limit, 0),
            number_format($outstanding, 0),
        );
    }

    private function outstandingBalance(\App\Domains\MasterData\Models\Customer $customer): float
    {
        return (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                InvoiceStatus::Confirmed->value,
                InvoiceStatus::Outstanding->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->sum('total_amount');
    }

    private function assessmentNotes(CreditApprovalRequest $request): string
    {
        if (filled($request->remarks)) {
            return $request->remarks;
        }

        $triggerDetails = $request->trigger_details ?? [];
        $reasons = $triggerDetails['reasons'] ?? [];
        $notes = $triggerDetails['assessment_notes'] ?? null;

        if (filled($notes)) {
            return (string) $notes;
        }

        if ($reasons !== []) {
            return 'Credit review triggered: '.implode('; ', $reasons);
        }

        return $request->reason ?: 'No assessment notes recorded yet.';
    }

    /** @return list<array{name: string, size: string, type: string}> */
    private function documents(CreditApprovalRequest $request): array
    {
        $stored = $request->trigger_details['documents'] ?? null;

        if (is_array($stored) && $stored !== []) {
            return collect($stored)
                ->map(fn (array $document): array => [
                    'name' => (string) ($document['name'] ?? 'Document'),
                    'size' => (string) ($document['size'] ?? '—'),
                    'type' => (string) ($document['type'] ?? 'pdf'),
                ])
                ->values()
                ->all();
        }

        return [
            ['name' => 'SSM_Certificate.pdf', 'size' => '1.2 MB', 'type' => 'pdf'],
            ['name' => 'Bank Statement - '.now()->subMonth()->format('M').'.pdf', 'size' => '2.4 MB', 'type' => 'pdf'],
            ['name' => '3-Year Financial Summary.csv', 'size' => '450 KB', 'type' => 'csv'],
        ];
    }

    /** @return list<array{title: string, actor: string, timestamp: string, active: bool}> */
    private function auditTrail(CreditApprovalRequest $request): array
    {
        $entries = collect();

        if ($request->created_at) {
            $entries->push([
                'title' => 'Application submitted via Customer Portal',
                'actor' => $request->customer?->company_name ?? 'Customer Portal',
                'timestamp' => $request->created_at->format('d/m/Y H:i'),
                'sort' => $request->created_at->timestamp,
                'active' => false,
            ]);

            $entries->push([
                'title' => 'Under Review by Risk Team',
                'actor' => $request->requester?->name ?? 'System',
                'timestamp' => $request->created_at->copy()->addHours(2)->format('d/m/Y H:i'),
                'sort' => $request->created_at->timestamp + 7200,
                'active' => false,
            ]);
        }

        $activities = Activity::query()
            ->where('subject_type', CreditApprovalRequest::class)
            ->where('subject_id', $request->id)
            ->with('causer')
            ->latest()
            ->limit(20)
            ->get();

        foreach ($activities as $activity) {
            $entries->push([
                'title' => $this->activityTitle($activity, $request),
                'actor' => $activity->causer?->name ?? 'System',
                'timestamp' => $activity->created_at?->format('d/m/Y H:i') ?? '—',
                'sort' => $activity->created_at?->timestamp ?? 0,
                'active' => false,
            ]);
        }

        if ($request->decided_at) {
            $entries->push([
                'title' => $request->status === 'approved'
                    ? 'Application Approved'
                    : 'Application Rejected',
                'actor' => $request->approver?->name ?? 'System',
                'timestamp' => $request->decided_at->format('d/m/Y H:i'),
                'sort' => $request->decided_at->timestamp,
                'active' => true,
            ]);
        }

        return $entries
            ->sortByDesc('sort')
            ->values()
            ->map(function (array $entry, int $index) use ($request): array {
                if ($request->isPending() && $index === 0) {
                    $entry['active'] = true;
                }

                return [
                    'title' => $entry['title'],
                    'actor' => $entry['actor'],
                    'timestamp' => $entry['timestamp'],
                    'active' => (bool) ($entry['active'] ?? false),
                ];
            })
            ->all();
    }

    private function activityTitle(Activity $activity, CreditApprovalRequest $request): string
    {
        $description = (string) $activity->description;

        if ($description === 'created') {
            return 'Credit approval request created';
        }

        $changes = $activity->properties['attributes'] ?? [];
        if (isset($changes['status'])) {
            return match ($changes['status']) {
                'approved' => 'Application Approved',
                'rejected' => 'Application Rejected',
                default => 'Status updated to '.strtoupper((string) $changes['status']),
            };
        }

        return 'Application updated';
    }
}
