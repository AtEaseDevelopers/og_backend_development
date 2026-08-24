<?php

namespace App\Support;

use App\Domains\Dispatch\Models\JobSheet;
use App\Filament\Resources\JobSheetResource;
use Filament\Facades\Filament;

class JobSheetListData
{
    /** @return array<string, mixed>|null */
    public function selectedPanel(?int $jobSheetId): ?array
    {
        if (! $jobSheetId) {
            return null;
        }

        $jobSheet = JobSheet::query()
            ->with([
                'operatingBranch',
                'lorry.defaultDriver',
                'driver',
            ])
            ->withCount('deliveryOrders')
            ->find($jobSheetId);

        if (! $jobSheet) {
            return null;
        }

        return [
            'id' => $jobSheet->id,
            'number' => $jobSheet->number,
            'operating_date' => $jobSheet->operating_date?->format('d/m/Y'),
            'operating_branch' => $jobSheet->operatingBranch?->name,
            'lorry' => $jobSheet->lorry?->registration_no,
            'default_driver' => $jobSheet->lorry?->defaultDriver?->name,
            'current_driver' => $jobSheet->driver?->name,
            'task_count' => (int) $jobSheet->delivery_orders_count,
            'status' => $jobSheet->status,
            'status_label' => $jobSheet->status?->getLabel(),
            'status_color' => $jobSheet->status?->getColor(),
            'view_url' => JobSheetResource::getUrl('view', ['record' => $jobSheet], true, null, Filament::getTenant()),
        ];
    }
}
