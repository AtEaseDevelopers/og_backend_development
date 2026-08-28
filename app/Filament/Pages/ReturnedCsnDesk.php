<?php

namespace App\Filament\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\FlagMissingCsns;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\MasterData\Models\Driver;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Support\ReturnedCsnReconciliationData;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class ReturnedCsnDesk extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Delivery';

    protected static ?string $navigationLabel = 'Returned CSNs';

    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.returned-csn-desk';

    public ?string $scanInput = null;

    /** @var array<int, array{consignment_note_id: int, is_signed: bool, is_stamped: bool, remarks: ?string, returned_by_driver_id: ?int}> */
    public array $reconciliationItems = [];

    public ?array $commissionBanner = null;

    public function mount(): void
    {
        $data = app(ReturnedCsnReconciliationData::class);

        foreach ($data->todaysReturnedItems() as $item) {
            $this->addReconciliationItem(
                (int) $item['consignment_note_id'],
                [
                    'is_signed' => (bool) ($item['is_signed'] ?? true),
                    'is_stamped' => (bool) ($item['is_stamped'] ?? false),
                    'remarks' => $item['remarks'] ?? null,
                    'returned_by_driver_id' => $item['returned_by_driver_id'] ?? null,
                ],
            );
        }
    }

    public function getHeading(): string
    {
        return 'Returned CSN Reconciliation';
    }

    public function getSubheading(): ?string
    {
        return 'Scan and review returned original CSNs and their linked delivery records.';
    }

    public function updatedScanInput(): void
    {
        $this->lookupScan();
    }

    public function lookupScan(): void
    {
        if (! filled($this->scanInput)) {
            return;
        }

        try {
            $detail = app(ReturnedCsnReconciliationData::class)->lookup((string) $this->scanInput);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not look up CSN')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (! $detail) {
            return;
        }

        $this->addReconciliationItem(
            (int) $detail['consignment_note_id'],
            [
                'is_signed' => (bool) ($detail['is_signed'] ?? true),
                'is_stamped' => (bool) ($detail['is_stamped'] ?? false),
                'remarks' => $detail['remarks'] ?? null,
                'returned_by_driver_id' => $detail['returned_by_driver_id'] ?? null,
            ],
        );
    }

    /** @return array<string, mixed>|null */
    public function getCurrentScanDetail(): ?array
    {
        if (! filled($this->scanInput)) {
            return null;
        }

        try {
            return app(ReturnedCsnReconciliationData::class)->lookup((string) $this->scanInput);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getListingDetails(): array
    {
        $data = app(ReturnedCsnReconciliationData::class);
        $details = [];

        foreach ($this->reconciliationItems as $index => $item) {
            $csn = ConsignmentNote::query()->find($item['consignment_note_id'] ?? null);

            if (! $csn) {
                continue;
            }

            $detail = $data->detailFromItemState($csn, $item);
            $detail['list_index'] = $index;
            $details[] = $detail;
        }

        return $details;
    }

    public function hasPendingReturns(): bool
    {
        foreach ($this->getListingDetails() as $detail) {
            if ($detail['eligible_for_return'] ?? false) {
                return true;
            }
        }

        return false;
    }

    public function saveReturn(): void
    {
        $saved = 0;
        $lastReturned = null;

        foreach ($this->reconciliationItems as $index => $item) {
            $csn = ConsignmentNote::query()->find($item['consignment_note_id'] ?? null);

            if (! $csn || $csn->returnedCsn()->exists()) {
                continue;
            }

            try {
                $lastReturned = app(RecordReturnedCsn::class)->execute($csn, [
                    'returned_by_driver_id' => $item['returned_by_driver_id'] ?? null,
                    'is_signed' => (bool) ($item['is_signed'] ?? true),
                    'is_stamped' => (bool) ($item['is_stamped'] ?? false),
                    'remarks' => $item['remarks'] ?? null,
                    'scan_method' => 'manual',
                ], auth()->user());

                $this->reconciliationItems[$index] = [
                    'consignment_note_id' => $csn->id,
                    'is_signed' => (bool) $lastReturned->is_signed,
                    'is_stamped' => (bool) $lastReturned->is_stamped,
                    'remarks' => $lastReturned->remarks,
                    'returned_by_driver_id' => $lastReturned->returned_by_driver_id,
                ];

                $saved++;
            } catch (Throwable $e) {
                Notification::make()
                    ->title("Could not save {$csn->number}")
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        if ($saved === 0) {
            Notification::make()
                ->title('No pending CSN returns to save.')
                ->warning()
                ->send();

            return;
        }

        if ($lastReturned) {
            $this->commissionBanner = app(ReturnedCsnReconciliationData::class)->commissionBanner($lastReturned);
        }

        Notification::make()
            ->title($saved === 1 ? 'CSN return recorded' : "{$saved} CSN returns recorded")
            ->body('Commission eligibility updated for assigned driver(s).')
            ->success()
            ->send();
    }

    public function flagMissing(): void
    {
        $logs = app(FlagMissingCsns::class)->execute();
        Notification::make()
            ->title('Missing CSN sweep complete')
            ->body($logs->count().' CSN(s) marked missing')
            ->success()
            ->send();
    }

    /** @return array<int|string, string> */
    public function driverOptions(): array
    {
        return Driver::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @param  array{is_signed?: bool, is_stamped?: bool, remarks?: ?string, returned_by_driver_id?: ?int}  $state */
    protected function addReconciliationItem(int $consignmentNoteId, array $state): void
    {
        foreach ($this->reconciliationItems as $item) {
            if ((int) ($item['consignment_note_id'] ?? 0) === $consignmentNoteId) {
                return;
            }
        }

        array_unshift($this->reconciliationItems, [
            'consignment_note_id' => $consignmentNoteId,
            'is_signed' => (bool) ($state['is_signed'] ?? true),
            'is_stamped' => (bool) ($state['is_stamped'] ?? false),
            'remarks' => $state['remarks'] ?? null,
            'returned_by_driver_id' => $state['returned_by_driver_id'] ?? null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToListing')
                ->label('Back to CSN Listing')
                ->color('gray')
                ->url(fn (): string => Filament::getTenant()
                    ? ConsignmentNoteResource::getUrl('index', [], true, null, Filament::getTenant())
                    : ConsignmentNoteResource::getUrl('index')),
            Actions\Action::make('saveReturn')
                ->label('Save')
                ->visible(fn (): bool => $this->hasPendingReturns())
                ->action(fn () => $this->saveReturn()),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'counter', 'storekeeper', 'finance']);
    }
}
