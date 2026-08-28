<?php

namespace App\Support;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\ReturnedCsn;
use App\Domains\MasterData\Models\Driver;
use App\Enums\CsnStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Facades\Filament;

class ReturnedCsnReconciliationData
{
    /** @return array<string, mixed>|null */
    public function lookup(string $input): ?array
    {
        $needle = trim(preg_replace('/^#\s*/', '', trim($input)) ?? '');

        if ($needle === '') {
            return null;
        }

        $query = ConsignmentNote::query()
            ->with([
                'customer',
                'deliveryOrders.jobSheet',
                'deliveryOrders.driver',
                'returnedCsn.returnedByDriver',
                'returnedCsn.receivedBy',
            ]);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        $csn = $query
            ->where(function ($q) use ($needle): void {
                $q->where('number', $needle)
                    ->orWhere('qr_token', $needle)
                    ->orWhere('number', 'like', '%'.$needle.'%');
            })
            ->orderByDesc('id')
            ->first();

        if (! $csn) {
            return null;
        }

        return $this->detailForCsn($csn);
    }

    /** @return array<string, mixed> */
    public function detailForCsn(ConsignmentNote $csn): array
    {
        $csn->loadMissing([
            'customer',
            'deliveryOrders.jobSheet',
            'deliveryOrders.driver',
            'returnedCsn.returnedByDriver',
            'returnedCsn.receivedBy',
        ]);

        $do = $csn->deliveryOrders->sortByDesc('id')->first();
        $returned = $csn->returnedCsn;
        $alreadyReturned = filled($returned);

        return [
            'consignment_note_id' => $csn->id,
            'csn_number' => $csn->number,
            'customer_name' => $csn->customer_name ?? $csn->customer?->name,
            'do_number' => $do?->number,
            'job_sheet_number' => $do?->jobSheet?->number,
            'returned_by' => $returned?->returnedByDriver?->name ?? $do?->driver?->name,
            'returned_by_driver_id' => $returned?->returned_by_driver_id ?? $do?->driver_id,
            'received_by' => $returned?->receivedBy?->name ?? auth()->user()?->name,
            'returned_at' => $returned?->returned_at?->format('d/m/Y H:i'),
            'is_signed' => $returned?->is_signed ?? true,
            'is_stamped' => $returned?->is_stamped ?? false,
            'remarks' => $returned?->remarks,
            'already_returned' => $alreadyReturned,
            'eligible_for_return' => ! $alreadyReturned,
            'status_tags' => $this->statusTags(
                $returned?->is_signed ?? true,
                $returned?->is_stamped ?? false,
            ),
            'list_url' => ($tenant = Filament::getTenant())
                ? ConsignmentNoteResource::getUrl('index', [], true, null, $tenant)
                : null,
        ];
    }

    /** @return array<int, array<string, string>> */
    public function statusTags(bool $isSigned, bool $isStamped): array
    {
        return [
            [
                'label' => 'Recipient Signature',
                'value' => $isSigned ? 'SIGNED' : 'UNSIGNED',
                'tone' => $isSigned ? 'success' : 'muted',
            ],
            [
                'label' => 'Customer Stamp',
                'value' => $isStamped ? 'STAMPED' : 'NOT STAMPED',
                'tone' => $isStamped ? 'info' : 'muted',
            ],
            [
                'label' => 'Original CSN Control',
                'value' => 'ONE ORIGINAL CSN',
                'tone' => 'info',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function commissionBanner(?ReturnedCsn $returned): ?array
    {
        if (! $returned) {
            return null;
        }

        $returned->loadMissing('deliveryOrder', 'consignmentNote');

        return [
            'message' => 'Commission eligibility updated for the assigned driver.',
            'related_do' => $returned->deliveryOrder?->number ?? $returned->consignmentNote?->do_number,
        ];
    }

    public function reconciliationDateLabel(): string
    {
        return now()->format('d/m/Y');
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingReturnItems(): array
    {
        $query = ConsignmentNote::query()
            ->where('status', CsnStatus::Delivered)
            ->whereIn('return_status', ['pending_return', 'missing'])
            ->whereDoesntHave('returnedCsn')
            ->with(['deliveryOrders.driver']);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (ConsignmentNote $csn): array {
                $do = $csn->deliveryOrders->sortByDesc('id')->first();

                return [
                    'consignment_note_id' => $csn->id,
                    'is_signed' => true,
                    'is_stamped' => false,
                    'remarks' => null,
                    'returned_by_driver_id' => $do?->driver_id,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function todaysReturnedItems(): array
    {
        $query = ReturnedCsn::query()
            ->with([
                'consignmentNote.customer',
                'consignmentNote.deliveryOrders.jobSheet',
                'consignmentNote.deliveryOrders.driver',
                'returnedByDriver',
                'receivedBy',
            ])
            ->whereDate('returned_at', today())
            ->latest('returned_at');

        if ($companyId = CurrentCompany::id()) {
            $query->whereHas('consignmentNote', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query
            ->get()
            ->map(function (ReturnedCsn $returned): array {
                $csn = $returned->consignmentNote;

                if (! $csn) {
                    return [];
                }

                return [
                    'consignment_note_id' => $csn->id,
                    'is_signed' => (bool) $returned->is_signed,
                    'is_stamped' => (bool) $returned->is_stamped,
                    'remarks' => $returned->remarks,
                    'returned_by_driver_id' => $returned->returned_by_driver_id,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @param  array{is_signed?: bool, is_stamped?: bool, remarks?: ?string, returned_by_driver_id?: ?int}  $state */
    public function detailFromItemState(ConsignmentNote $csn, array $state): array
    {
        $detail = $this->detailForCsn($csn);
        $isSigned = (bool) ($state['is_signed'] ?? $detail['is_signed'] ?? true);
        $isStamped = (bool) ($state['is_stamped'] ?? $detail['is_stamped'] ?? false);

        $detail['is_signed'] = $isSigned;
        $detail['is_stamped'] = $isStamped;
        $detail['remarks'] = $state['remarks'] ?? $detail['remarks'] ?? null;
        $detail['returned_by_driver_id'] = $state['returned_by_driver_id'] ?? $detail['returned_by_driver_id'] ?? null;
        $detail['returned_by'] = filled($detail['returned_by_driver_id'])
            ? Driver::query()->find($detail['returned_by_driver_id'])?->name
            : ($detail['returned_by'] ?? null);
        $detail['status_tags'] = $this->statusTags($isSigned, $isStamped);

        return $detail;
    }
}
