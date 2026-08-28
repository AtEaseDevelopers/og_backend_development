<?php

namespace App\Support;

use App\Domains\Delivery\Models\FailedDelivery;
use App\Enums\DeliveryOrderStatus;
use App\Filament\Resources\FailedDeliveryResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FailedDeliveryReviewData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $date = filled($filters['delivery_date'] ?? null)
            ? Carbon::parse((string) $filters['delivery_date'])->startOfDay()
            : now()->startOfDay();

        $rows = $this->query($filters, $date)
            ->orderByDesc('failed_at')
            ->get()
            ->map(fn (FailedDelivery $failed): array => $this->formatRow($failed, $date));

        return [
            'selected_date_label' => $date->format('d/m/Y'),
            'rows' => $rows->all(),
            'count' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters, Carbon $date): Builder
    {
        $query = FailedDelivery::query()
            ->with([
                'deliveryOrder.consignmentNote',
                'deliveryOrder.sourceBranch',
                'deliveryOrder.jobSheet.operatingBranch',
                'deliveryOrder.lorry',
                'driver',
            ])
            ->whereHas('deliveryOrder', function (Builder $do) use ($filters, $date): void {
                $do->where('status', DeliveryOrderStatus::Failed);

                if ($companyId = CurrentCompany::id()) {
                    $do->where('company_id', $companyId);
                }

                $do->whereHas('jobSheet', fn (Builder $js) => $js->whereDate('operating_date', $date));

                if (filled($filters['branch_id'] ?? null)) {
                    $do->where('source_branch_id', $filters['branch_id']);
                }

                if (filled($filters['job_sheet_id'] ?? null)) {
                    $do->where('job_sheet_id', $filters['job_sheet_id']);
                }

                if (filled($filters['driver_id'] ?? null)) {
                    $do->where('driver_id', $filters['driver_id']);
                }

                if (filled($filters['lorry_id'] ?? null)) {
                    $do->where('lorry_id', $filters['lorry_id']);
                }

                if (filled($filters['search'] ?? null)) {
                    $needle = trim((string) $filters['search']);

                    $do->where(function (Builder $q) use ($needle): void {
                        $q->where('number', 'like', '%'.$needle.'%')
                            ->orWhereHas('consignmentNote', fn (Builder $csn) => $csn->where('number', 'like', '%'.$needle.'%'))
                            ->orWhereHas('jobSheet', fn (Builder $js) => $js->where('number', 'like', '%'.$needle.'%'));
                    });
                }
            });

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(FailedDelivery $failed, Carbon $date): array
    {
        $do = $failed->deliveryOrder;
        $csn = $do?->consignmentNote;

        return [
            'id' => $failed->id,
            'do_number' => $do?->number,
            'view_url' => static::viewUrl($failed),
            'job_sheet_number' => $do?->jobSheet?->number,
            'branch' => $do?->sourceBranch?->name ?? $do?->jobSheet?->operatingBranch?->name,
            'driver' => $failed->driver?->name ?? $do?->driver?->name,
            'lorry' => $do?->lorry?->registration_no,
            'destination' => $this->formatDestination($csn?->delivery_city, $csn?->delivery_state, $csn?->delivery_address),
            'date' => $do?->jobSheet?->operating_date?->format('d/m/Y') ?? $failed->failed_at?->format('d/m/Y') ?? $date->format('d/m/Y'),
            'status_label' => 'Failed',
        ];
    }

    public static function viewUrl(FailedDelivery $failed): string
    {
        return Filament::getTenant()
            ? FailedDeliveryResource::getUrl('view', ['record' => $failed], true, null, Filament::getTenant())
            : FailedDeliveryResource::getUrl('view', ['record' => $failed]);
    }

    private function formatDestination(?string $city, ?string $state, ?string $address = null): string
    {
        $parts = array_filter([$city, $state]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return filled($address) ? (string) $address : '—';
    }
}
