<?php

namespace App\Support;

use App\Domains\Delivery\Models\MissingCsnLog;
use App\Filament\Resources\MissingCsnLogResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MissingCsnMonitoringData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $asOf = filled($filters['monitoring_date'] ?? null)
            ? Carbon::parse((string) $filters['monitoring_date'])->endOfDay()
            : now()->endOfDay();

        $graceDays = (int) config('og.missing_csn_days', 7);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 4;

        $rows = $this->query($filters, $asOf)
            ->orderByRaw("CASE WHEN status = 'missing' THEN 0 ELSE 1 END")
            ->orderByDesc('marked_missing_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MissingCsnLog $log): array => $this->formatRow($log, $asOf, $graceDays));

        if (($filters['status'] ?? null) === 'missing') {
            $rows = $rows->where('status', 'missing')->values();
        }

        $stats = [
            'unreturned' => $this->statsQuery($filters, $asOf)->count(),
            'pending_return' => $this->statsQuery($filters, $asOf)->where('status', 'pending_return')->count(),
            'missing' => $this->statsQuery($filters, $asOf)->where('status', 'missing')->count(),
        ];

        $total = $rows->count();
        $paginated = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'monitoring_date_label' => $asOf->format('d/m/Y'),
            'grace_days' => $graceDays,
            'stats' => $stats,
            'rows' => $paginated->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to' => min($total, $page * $perPage),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function statsQuery(array $filters, Carbon $asOf): Builder
    {
        return $this->query($filters, $asOf);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters, Carbon $asOf): Builder
    {
        $query = MissingCsnLog::query()
            ->whereIn('status', ['pending_return', 'missing'])
            ->with([
                'consignmentNote.customer',
                'deliveryOrder.driver',
                'deliveryOrder.lorry',
                'deliveryOrder.jobSheet',
                'sourceBranch',
            ]);

        if ($companyId = CurrentCompany::id()) {
            $query->where(function (Builder $q) use ($companyId): void {
                $q->where('company_id', $companyId)
                    ->orWhereHas('deliveryOrder', fn (Builder $do) => $do->where('company_id', $companyId))
                    ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('company_id', $companyId));
            });
        }

        $query->whereHas('deliveryOrder', function (Builder $do) use ($filters, $asOf): void {
            $do->whereNotNull('delivered_at')
                ->where('delivered_at', '<=', $asOf);

            if (filled($filters['branch_id'] ?? null)) {
                $do->where('source_branch_id', $filters['branch_id']);
            }

            if (filled($filters['driver_id'] ?? null)) {
                $do->where('driver_id', $filters['driver_id']);
            }

            if (filled($filters['lorry_id'] ?? null)) {
                $do->where('lorry_id', $filters['lorry_id']);
            }

            if (filled($filters['job_sheet_id'] ?? null)) {
                $do->where('job_sheet_id', $filters['job_sheet_id']);
            }
        });

        if (filled($filters['customer_id'] ?? null)) {
            $query->whereHas('consignmentNote', fn (Builder $csn) => $csn->where('customer_id', $filters['customer_id']));
        }

        if (filled($filters['csn_search'] ?? null)) {
            $needle = trim((string) $filters['csn_search']);

            $query->whereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(MissingCsnLog $log, Carbon $asOf, int $graceDays): array
    {
        $do = $log->deliveryOrder;
        $csn = $log->consignmentNote;
        $deliveredAt = $do?->delivered_at;
        $returnDueDate = $deliveredAt?->copy()->addDays($graceDays);

        $daysSinceDue = null;
        if ($log->status === 'missing' && $returnDueDate) {
            $daysSinceDue = (int) $returnDueDate->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
        }

        $tenant = Filament::getTenant();
        $viewUrl = null;

        if ($tenant) {
            $viewUrl = MissingCsnLogResource::getUrl('view', ['record' => $log], true, null, $tenant);
        }

        return [
            'id' => $log->id,
            'csn_number' => $csn?->number,
            'job_sheet_number' => $do?->jobSheet?->number,
            'branch_name' => $log->sourceBranch?->name ?? $log->sourceBranch?->code,
            'driver_name' => $do?->driver?->name,
            'lorry_registration' => $do?->lorry?->registration_no,
            'customer_name' => $csn?->customer_name ?? $csn?->customer?->company_name ?? $csn?->customer?->name,
            'return_due_date' => $returnDueDate?->format('d/m/Y'),
            'days_since_due' => $daysSinceDue,
            'days_since_due_label' => $daysSinceDue !== null ? (string) $daysSinceDue : '—',
            'status' => $log->status,
            'status_label' => match ($log->status) {
                'pending_return' => 'PENDING RETURN',
                'missing' => 'MISSING',
                default => strtoupper((string) $log->status),
            },
            'is_missing' => $log->status === 'missing',
            'view_url' => $viewUrl,
        ];
    }
}
