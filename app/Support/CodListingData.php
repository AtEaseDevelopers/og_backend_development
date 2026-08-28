<?php

namespace App\Support;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Enums\CsnBillingType;
use App\Enums\JobSheetStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\JobSheetResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CodListingData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $date = filled($filters['date'] ?? null)
            ? Carbon::parse((string) $filters['date'])->startOfDay()
            : now()->startOfDay();

        $jobSheets = $this->query($filters, $date)
            ->orderByDesc('id')
            ->get()
            ->map(fn (JobSheet $jobSheet): array => $this->formatRow($jobSheet));

        return [
            'selected_date' => $date->format('Y-m-d'),
            'selected_date_label' => $date->format('d/m/Y'),
            'rows' => $jobSheets->values()->all(),
            'count' => $jobSheets->count(),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters, Carbon $date): Builder
    {
        $query = JobSheet::query()
            ->whereDate('operating_date', $date)
            ->whereHas('deliveryOrders.consignmentNote', fn (Builder $q) => $q->where('billing_type', CsnBillingType::Cod))
            ->with([
                'driver',
                'lorry',
                'deliveryOrders.consignmentNote',
            ]);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($needle): void {
                $builder->where('number', 'like', '%'.$needle.'%')
                    ->orWhereHas('driver', fn (Builder $driver) => $driver->where('name', 'like', '%'.$needle.'%'))
                    ->orWhereHas('lorry', fn (Builder $lorry) => $lorry->where('registration_no', 'like', '%'.$needle.'%'));
            });
        }

        if (($filters['status'] ?? '') === 'in_progress') {
            $query->where('status', '!=', JobSheetStatus::Completed->value);
        } elseif (($filters['status'] ?? '') === 'completed') {
            $query->where('status', JobSheetStatus::Completed->value);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(JobSheet $jobSheet): array
    {
        $codOrders = $jobSheet->deliveryOrders
            ->filter(fn (DeliveryOrder $order): bool => $order->consignmentNote?->billing_type === CsnBillingType::Cod);

        $totalCod = (float) $codOrders->sum(fn (DeliveryOrder $order): float => (float) ($order->consignmentNote?->total_amount ?? 0));
        $status = $this->rowStatus($jobSheet, $codOrders);

        return [
            'id' => $jobSheet->id,
            'driver' => $jobSheet->driver?->name ?? '—',
            'lorry' => $this->formatLorryPlate($jobSheet->lorry?->registration_no),
            'job_sheet' => $jobSheet->number,
            'cod_deliveries' => $codOrders->count(),
            'total_cod' => $totalCod,
            'total_cod_label' => 'MYR '.number_format($totalCod, 2),
            'status' => $status,
            'status_label' => strtoupper(str_replace('_', ' ', $status)),
            'view_url' => JobSheetResource::getUrl('view', ['record' => $jobSheet]),
        ];
    }

    /** @param  Collection<int, DeliveryOrder>  $codOrders */
    private function rowStatus(JobSheet $jobSheet, Collection $codOrders): string
    {
        if ($jobSheet->status === JobSheetStatus::Completed) {
            return 'completed';
        }

        if ($codOrders->isNotEmpty()) {
            $allDelivered = $codOrders->every(fn (DeliveryOrder $order): bool => $order->status?->value === 'delivered');
            $allCollected = $codOrders->every(function (DeliveryOrder $order): bool {
                $paymentStatus = $order->consignmentNote?->payment_status;

                return in_array($paymentStatus, [
                    PaymentStatus::CodCollected,
                    PaymentStatus::CodReconciled,
                    PaymentStatus::Paid,
                ], true);
            });

            if ($allDelivered && $allCollected) {
                return 'completed';
            }
        }

        return 'in_progress';
    }

    private function formatLorryPlate(?string $registration): string
    {
        if (blank($registration)) {
            return '—';
        }

        if (str_contains($registration, ' ')) {
            return strtoupper($registration);
        }

        if (preg_match('/^([A-Z]+)(\d+)$/', strtoupper($registration), $matches)) {
            return $matches[1].' '.$matches[2];
        }

        return strtoupper($registration);
    }

    /** @return array<string, string> */
    public static function statusFilterOptions(): array
    {
        return [
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ];
    }
}
