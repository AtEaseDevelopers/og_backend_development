<?php

namespace App\Filament\Resources\ConsignmentNoteResource\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\Delivery\Actions\UndoReturnedCsn;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\MasterData\Models\TransferCode;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Models\User;
use App\Support\CurrentCompany;
use Closure;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;
use App\Filament\Concerns\HasExcelColumnFilters;

/**
 * CSN management. Filters work like the Orders page: a search with the CSN / job / created date ranges
 * beside it, four main filters and a "Filters +" panel, all kept in the URL. Above the table a 7-day
 * CSN date strip shows how many CSNs fall on each day; clicking a day shows only that day.
 * The CSN date starts on today; the table columns only sort (all filtering is in the filter card).
 */
class ListConsignmentNotes extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = ConsignmentNoteResource::class;

    protected static string $view = 'filament.resources.consignment-note-resource.pages.list-consignment-notes';

    /** Every filter bar property: changing one goes back to page 1, Reset clears them all (the CSN date goes back to today). */
    protected const FILTERS = [
        'search', 'csnFrom', 'csnTo', 'jobFrom', 'jobTo', 'createdFrom', 'createdTo',
        'customer', 'orderType', 'serviceType', 'paymentStatus',
        'billingType', 'saLocation', 'transferCode', 'salesperson', 'driver', 'lorry', 'quotation',
        'claimed', 'assigned', 'hasSubsheets', 'amountMin', 'amountMax',
    ];

    /** The fields behind "Filters +": the panel opens on load when one of them is set. */
    protected const PANEL_FILTERS = [
        'billingType', 'saLocation', 'transferCode', 'salesperson', 'driver', 'lorry', 'quotation',
        'claimed', 'assigned', 'hasSubsheets', 'amountMin', 'amountMax',
    ];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** CSN date (issued_at, the date printed on the CSN). Kept in the URL by queryString(), except for today. */
    public string $csnFrom = '';

    public string $csnTo = '';

    /**
     * 'all' while the CSN date is cleared (every date shows), so a refresh or Back keeps it cleared
     * instead of going back to today. Kept in step with the CSN date by syncCsnScope().
     */
    #[Url(as: 'csn', except: '')]
    public string $csnScope = '';

    #[Url(as: 'job_from', except: '')]
    public string $jobFrom = '';

    #[Url(as: 'job_until', except: '')]
    public string $jobTo = '';

    #[Url(as: 'created_from', except: '')]
    public string $createdFrom = '';

    #[Url(as: 'created_until', except: '')]
    public string $createdTo = '';

    #[Url(as: 'customer', except: '')]
    public string $customer = '';

    #[Url(as: 'type', except: '')]
    public string $orderType = '';

    #[Url(as: 'service', except: '')]
    public string $serviceType = '';

    #[Url(as: 'payment', except: '')]
    public string $paymentStatus = '';

    #[Url(as: 'billing', except: '')]
    public string $billingType = '';

    #[Url(as: 'sa', except: '')]
    public string $saLocation = '';

    #[Url(as: 'transfer', except: '')]
    public string $transferCode = '';

    #[Url(as: 'salesperson', except: '')]
    public string $salesperson = '';

    #[Url(as: 'driver', except: '')]
    public string $driver = '';

    #[Url(as: 'lorry', except: '')]
    public string $lorry = '';

    /** The order (quotation) the CSNs were created from. */
    #[Url(as: 'order', except: '')]
    public string $quotation = '';

    /** '' = all, '1' = yes, '0' = no. */
    #[Url(as: 'claimed', except: '')]
    public string $claimed = '';

    #[Url(as: 'assigned', except: '')]
    public string $assigned = '';

    #[Url(as: 'subsheets', except: '')]
    public string $hasSubsheets = '';

    #[Url(as: 'min', except: '')]
    public string $amountMin = '';

    #[Url(as: 'max', except: '')]
    public string $amountMax = '';

    public bool $filtersOpen = false;

    /**
     * Subsheet lines ticked under their CSNs (ids of subsheets without a lorry yet): assigned to a lorry with
     * "Assign to lorry", alone or together with the CSNs ticked in the table.
     *
     * @var list<int|string>
     */
    public array $selectedSubsheets = [];

    /** First day (Y-m-d) of the 7-day CSN date strip. */
    public string $stripStart = '';

    /**
     * The CSN date goes into the URL like the #[Url] filters, but today (the default) is left out, so a page
     * refreshed or bookmarked on a later day opens on that day. A cleared date is kept by the "csn=all" marker.
     */
    protected function queryString(): array
    {
        $today = today()->toDateString();

        return [
            'csnFrom' => ['as' => 'csn_from', 'history' => false, 'except' => $today],
            'csnTo' => ['as' => 'csn_until', 'history' => false, 'except' => $today],
        ];
    }

    public function mount(): void
    {
        // Old-style links (an order's "View CSN", the old table search) point at particular CSNs
        $isLegacyLink = filled($this->tableFilters) || (is_scalar($this->tableSearch) && filled(trim((string) $this->tableSearch)));

        $this->applyLegacyTableFilters();

        parent::mount();

        $this->applyDefaultCsnDate(targetsRecords: $isLegacyLink || filled(trim($this->search)) || filled($this->quotation));

        // Only the "Filters +" panel fields; the date ranges sit next to the search and are always shown
        $this->filtersOpen = collect(self::PANEL_FILTERS)->contains(fn (string $property): bool => filled($this->{$property}));

        $this->centreStrip($this->csnFrom ?: $this->csnTo);
    }

    public function getHeading(): string
    {
        return 'CSN management';
    }

    /** The DO count comes from the table's DO column (->counts()), so it is not added here as well. */
    protected function getTableQuery(): Builder
    {
        return $this->applyFilterBar(parent::getTableQuery())
            ->with(['deliveryOrder.lorry', 'deliveryOrders.failedDelivery', 'quotation', 'transferCode', 'subsheets.subLorry', 'returnedCsn.receivedBy'])
            ->withCount('breakBulks');
    }

    /**
     * Stage tabs (section H): All / Unassigned / Claimed / Assigned / In transit / Delivered / Cancelled.
     * Badges count within the filter bar, like the stage tags on the Orders page.
     * The tab closures must name their argument $query: Filament injects the table query by that name.
     */
    public function getTabs(): array
    {
        $awaiting = [CsnStatus::PendingAssignment->value, CsnStatus::Confirmed->value, CsnStatus::Draft->value];
        $filtered = fn (): Builder => $this->applyFilterBar(ConsignmentNoteResource::getEloquentQuery());
        $status = fn (CsnStatus $status): Closure => fn (Builder $query): Builder => $query->where('status', $status->value);
        $unassigned = fn (Builder $query): Builder => $query->whereIn('status', $awaiting)->whereNull('claimed_by')->whereDoesntHave('deliveryOrder');
        $claimed = fn (Builder $query): Builder => $query->whereIn('status', $awaiting)->whereNotNull('claimed_by')->whereDoesntHave('deliveryOrder');
        $assigned = $status(CsnStatus::Assigned);
        $inTransit = $status(CsnStatus::InTransit);
        $delivered = $status(CsnStatus::Delivered);
        // the latest main DO failed (not delivered or cancelled since)
        $failed = fn (Builder $query): Builder => $query
            ->whereNotIn('status', [CsnStatus::Delivered->value, CsnStatus::Cancelled->value])
            ->whereHas('deliveryOrders', fn (Builder $do): Builder => $do->whereNull('parent_do_id')->whereNull('subsheet_id')->where('status', 'failed')
                ->whereRaw('delivery_orders.id = (select max(d2.id) from delivery_orders d2 where d2.consignment_note_id = delivery_orders.consignment_note_id and d2.parent_do_id is null and d2.subsheet_id is null)'));

        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => $filtered()->count()),
            'unassigned' => Tab::make('Unassigned')
                ->badge(fn (): int => $unassigned($filtered())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing($unassigned),
            'claimed' => Tab::make('Claimed')
                ->badge(fn (): int => $claimed($filtered())->count())
                ->modifyQueryUsing($claimed),
            'assigned' => Tab::make('Assigned')
                ->badge(fn (): int => $assigned($filtered())->count())
                ->modifyQueryUsing($assigned),
            'in_transit' => Tab::make('In transit')
                ->badge(fn (): int => $inTransit($filtered())->count())
                ->modifyQueryUsing($inTransit),
            'failed' => Tab::make('Failed delivery')
                ->badge(fn (): int => $failed($filtered())->count())
                ->badgeColor('danger')
                ->modifyQueryUsing($failed),
            'delivered' => Tab::make('Delivered')
                ->badge(fn (): int => $delivered($filtered())->count())
                ->badgeColor('success')
                ->modifyQueryUsing($delivered),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing($status(CsnStatus::Cancelled)),
        ];
    }

    /** Return scan: what was typed / scanned, and the CSNs scanned in this session (newest first). */
    public string $returnScan = '';

    /** @var list<array{id: int, number: string, customer: ?string, returned: bool, message: string, tone: string}> */
    public array $returnScanLog = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scanReturned')
                ->label('Scan returned CSN')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->modalHeading('Scan returned CSNs')
                ->modalDescription('Scan the QR code on each original CSN the driver brings back (camera or handheld scanner), or type the CSN number and press Enter. Each scan marks the CSN as returned.')
                ->modalContent(fn () => view('filament.resources.consignment-note-resource.partials.return-scan'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Done')
                ->modalWidth('2xl')
                ->mountUsing(function (): void {
                    $this->returnScan = '';
                    $this->returnScanLog = [];
                }),
            Actions\CreateAction::make()->label('New consignment note'),
        ];
    }

    /** A scanned / typed CSN number (or the CSN's QR token) is marked as returned. */
    public function scanReturnedCsn(?string $value = null): void
    {
        $needle = trim((string) ($value ?? $this->returnScan));
        $this->returnScan = '';

        if ($needle === '') {
            return;
        }

        $csn = ConsignmentNote::query()
            ->with(['customer', 'returnedCsn'])
            ->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id))
            ->where(fn (Builder $q) => $q->where('number', $needle)->orWhere('qr_token', $needle))
            ->first();

        if (! $csn) {
            $this->logReturnScan(null, $needle, null, false, 'No CSN found for "'.$needle.'".', 'danger');

            return;
        }

        if ($csn->return_status === 'returned' || $csn->returnedCsn) {
            $this->logReturnScan($csn->id, $csn->number, $csn->customer_name, true, 'Already marked as returned'.($csn->returnedCsn?->returned_at ? ' on '.$csn->returnedCsn->returned_at->format('d/m/Y H:i') : '').'.', 'warning');

            return;
        }

        try {
            app(RecordReturnedCsn::class)->execute($csn, ['scan_method' => 'qr'], auth()->user());
            $this->logReturnScan($csn->id, $csn->number, $csn->customer_name, true, 'Marked as returned.', 'success');
        } catch (Throwable $e) {
            $this->logReturnScan($csn->id, $csn->number, $csn->customer_name, false, $e->getMessage(), 'danger');
        }
    }

    /** Undo a return scan from the scan window: the CSN is not back after all. */
    public function markCsnNotReturned(int $csnId): void
    {
        $csn = ConsignmentNote::query()->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id))->find($csnId);

        if (! $csn) {
            return;
        }

        try {
            app(UndoReturnedCsn::class)->execute($csn, auth()->user());
            $this->returnScanLog = array_map(fn (array $row) => $row['id'] === $csnId
                ? array_merge($row, ['returned' => false, 'message' => 'Marked as not returned.', 'tone' => 'gray'])
                : $row, $this->returnScanLog);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private function logReturnScan(?int $id, string $number, ?string $customer, bool $returned, string $message, string $tone): void
    {
        array_unshift($this->returnScanLog, ['id' => (int) $id, 'number' => $number, 'customer' => $customer, 'returned' => $returned, 'message' => $message, 'tone' => $tone]);
        $this->returnScanLog = array_slice($this->returnScanLog, 0, 30);
    }

    /*
    |--------------------------------------------------------------------------
    | Filter bar
    |--------------------------------------------------------------------------
    */

    /**
     * Narrows a CSN query to the filter bar; the table, the tab badges and the date strip share it.
     * Same conditions as the Filament filters it replaces, plus a wider search.
     *
     * @param  bool  $withCsnDate  false for the date strip, which counts every day of its window
     */
    protected function applyFilterBar(Builder $query, bool $withCsnDate = true): Builder
    {
        if (filled($search = trim($this->search))) {
            $like = '%'.$search.'%';

            $query->where(fn (Builder $query): Builder => $query
                ->where('number', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_do_number', 'like', $like)
                ->orWhere('do_number', 'like', $like)
                ->orWhere('invoice_number', 'like', $like)
                ->orWhereHas('quotation', fn (Builder $query): Builder => $query->where('number', 'like', $like))
                ->orWhereHas('deliveryOrders', fn (Builder $query): Builder => $query->where('number', 'like', $like)));
        }

        $dateRanges = [
            ['issued_at', $withCsnDate ? $this->csnFrom : '', $withCsnDate ? $this->csnTo : ''],
            ['job_date', $this->jobFrom, $this->jobTo],
            ['created_at', $this->createdFrom, $this->createdTo],
        ];

        foreach ($dateRanges as [$column, $from, $to]) {
            if ($from = $this->dateOrNull($from)) {
                $query->whereDate($column, '>=', $from);
            }

            if ($to = $this->dateOrNull($to)) {
                $query->whereDate($column, '<=', $to);
            }
        }

        $equals = [
            'customer_id' => $this->customer,
            'order_type' => $this->orderType,
            'service_type' => $this->serviceType,
            'payment_status' => $this->paymentStatus,
            'billing_type' => $this->billingType,
            'sa_location_id' => $this->saLocation,
            'salesperson_id' => $this->salesperson,
            'quotation_id' => $this->quotation,
        ];

        foreach ($equals as $column => $value) {
            if (filled($value)) {
                $query->where($column, $value);
            }
        }

        if (filled($this->transferCode)) {
            $this->withTransferCode($query, $this->transferCode);
        }

        if (filled($this->driver)) {
            $query->whereHas('deliveryOrder', fn (Builder $query): Builder => $query->where('driver_id', $this->driver));
        }

        if (filled($this->lorry)) {
            $query->whereHas('deliveryOrder', fn (Builder $query): Builder => $query->where('lorry_id', $this->lorry));
        }

        match ($this->claimed) {
            '1' => $query->whereNotNull('claimed_by'),
            '0' => $query->whereNull('claimed_by'),
            default => null,
        };

        match ($this->assigned) {
            '1' => $query->whereHas('deliveryOrder'),
            '0' => $query->whereDoesntHave('deliveryOrder'),
            default => null,
        };

        match ($this->hasSubsheets) {
            '1' => $query->whereHas('subsheets'),
            '0' => $query->whereDoesntHave('subsheets'),
            default => null,
        };

        if (is_numeric($this->amountMin)) {
            $query->where('total_amount', '>=', $this->amountMin);
        }

        if (is_numeric($this->amountMax)) {
            $query->where('total_amount', '<=', $this->amountMax);
        }

        return $query;
    }

    public function updated(string $property): void
    {
        if (! in_array($property, self::FILTERS, true)) {
            return;
        }

        $this->resetPage();

        // A CSN date picked on the calendar brings that day into the strip; clearing it shows every date
        if (in_array($property, ['csnFrom', 'csnTo'], true)) {
            $this->syncCsnScope();

            $day = $this->dateOrNull($this->csnFrom) ?? $this->dateOrNull($this->csnTo);

            if ($day !== null && ! $this->stripShows($day)) {
                $this->centreStrip($day);
            }
        }
    }

    /** The ticked subsheet lines that can still go on a lorry (no lorry yet, CSN not cancelled, this company). */
    public function selectedSubsheetRecords(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->selectedSubsheets)));

        if ($ids === []) {
            return collect();
        }

        return Subsheet::query()
            ->whereIn('id', $ids)
            ->whereNull('delivery_order_id')
            ->whereIn('consignment_note_id', ConsignmentNoteResource::getEloquentQuery()->where('status', '!=', CsnStatus::Cancelled->value)->select('consignment_notes.id'))
            ->with('consignmentNote')
            ->orderBy('id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Service / Transfer code toggles (beside the status toggle)
    |--------------------------------------------------------------------------
    */

    /** @return list<array{value: string, label: string, count: int}> */
    public function serviceToggles(): array
    {
        $counts = $this->filteredWithout('serviceType')
            ->toBase()
            ->selectRaw('service_type, count(*) as aggregate')
            ->groupBy('service_type')
            ->pluck('aggregate', 'service_type');

        return collect(['' => 'All'] + ServiceType::options())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $value === '' ? 'All' : $label,
                'count' => (int) ($value === '' ? $counts->sum() : ($counts[$value] ?? 0)),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string, title: string, count: int}> */
    public function transferToggles(): array
    {
        $codes = TransferCode::query()->where('is_active', true)->orderBy('code')->get();

        return collect([['value' => '', 'label' => 'All', 'title' => 'Any transfer code', 'count' => $this->filteredWithout('transferCode')->count()]])
            ->concat($codes->map(fn (TransferCode $code): array => [
                'value' => (string) $code->id,
                'label' => $code->code,
                'title' => filled($code->name) ? $code->code.' — '.$code->name : $code->code,
                'count' => $this->withTransferCode($this->filteredWithout('transferCode'), (string) $code->id)->count(),
            ]))
            ->values()
            ->all();
    }

    /** The CSN list with every filter except one (its own toggle shows the counts of its choices). */
    protected function filteredWithout(string $property): Builder
    {
        $saved = $this->{$property};
        $this->{$property} = '';

        try {
            return $this->applyFilterBar(ConsignmentNoteResource::getEloquentQuery());
        } finally {
            $this->{$property} = $saved;
        }
    }

    /** CSNs of a transfer code: set on the CSN, or on one of its subsheets. */
    protected function withTransferCode(Builder $query, string $id): Builder
    {
        $code = TransferCode::query()->whereKey($id)->value('code');

        return $query->where(fn (Builder $q) => $q->where('transfer_code_id', $id)
            ->when($code, fn (Builder $w) => $w->orWhereHas('subsheets', fn (Builder $s) => $s->where('transfer_code', $code))));
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    /** Back to the default view: no filters, the first tab, and today's CSNs with today in the middle of the strip. */
    public function resetFilters(): void
    {
        foreach (self::FILTERS as $property) {
            $this->{$property} = '';
        }

        $this->csnFrom = $this->csnTo = today()->toDateString();
        $this->syncCsnScope();
        $this->activeTab = $this->getDefaultActiveTab();
        $this->centreStrip();
        $this->resetPage();
    }

    /**
     * A fresh visit shows today's CSNs. Left as it is: a CSN date already in the URL, the "all dates" marker
     * (the date was cleared before), and links that point at particular CSNs (an order, a search), which show every date.
     */
    protected function applyDefaultCsnDate(bool $targetsRecords): void
    {
        if (blank($this->csnFrom) && blank($this->csnTo) && $this->csnScope !== 'all' && ! $targetsRecords) {
            $this->csnFrom = $this->csnTo = today()->toDateString();
        }

        $this->syncCsnScope();
    }

    /** The "all dates" marker is set exactly while the CSN date is empty. */
    protected function syncCsnScope(): void
    {
        $this->csnScope = blank($this->csnFrom) && blank($this->csnTo) ? 'all' : '';
    }

    /**
     * Links built for the old Filament filters (e.g. "View CSN" on an order:
     * ?tableFilters[quotation_id][value]=ID) and the old global search (?tableSearch=X) are mapped onto
     * the filter bar, then dropped from the URL.
     */
    protected function applyLegacyTableFilters(): void
    {
        $filters = is_array($this->tableFilters) ? $this->tableFilters : [];
        $this->tableFilters = null;

        $set = function (string $property, mixed $value): void {
            if (is_scalar($value) && filled($value) && blank($this->{$property})) {
                $this->{$property} = (string) $value;
            }
        };

        // No column is globally searchable any more, so the old search would only show a chip without filtering
        $set('search', is_scalar($this->tableSearch) ? trim((string) $this->tableSearch) : null);
        $this->tableSearch = '';

        $selects = [
            'customer_id' => 'customer', 'order_type' => 'orderType', 'service_type' => 'serviceType',
            'payment_status' => 'paymentStatus', 'billing_type' => 'billingType', 'sa_location_id' => 'saLocation',
            'transfer_code_id' => 'transferCode', 'salesperson_id' => 'salesperson', 'driver_id' => 'driver',
            'main_lorry_id' => 'lorry', 'quotation_id' => 'quotation',
        ];

        foreach ($selects as $filter => $property) {
            $set($property, data_get($filters, $filter.'.value'));
        }

        foreach (['claimed' => 'claimed', 'assigned' => 'assigned', 'has_subsheets' => 'hasSubsheets'] as $filter => $property) {
            $value = data_get($filters, $filter.'.value');
            $set($property, match (true) {
                in_array($value, [true, 1, '1', 'true'], true) => '1',
                in_array($value, [false, 0, '0', 'false'], true) => '0',
                default => null,
            });
        }

        foreach (['issued_at' => ['csnFrom', 'csnTo'], 'job_date' => ['jobFrom', 'jobTo'], 'created_at' => ['createdFrom', 'createdTo']] as $filter => [$from, $to]) {
            $set($from, $this->legacyDate(data_get($filters, $filter.'.from')));
            $set($to, $this->legacyDate(data_get($filters, $filter.'.until')));
        }

        $set('amountMin', is_numeric($min = data_get($filters, 'total_amount.min')) ? $min : null);
        $set('amountMax', is_numeric($max = data_get($filters, 'total_amount.max')) ? $max : null);

        // The old status filter: the matching stage tab, where there is one
        $tab = data_get($filters, 'status.value');
        if (blank($this->activeTab) && in_array($tab, ['assigned', 'in_transit', 'delivered', 'cancelled'], true)) {
            $this->activeTab = $tab;
        }
    }

    protected function legacyDate(mixed $value): ?string
    {
        return is_string($value) && filled($value)
            ? rescue(fn (): string => Carbon::parse($value)->toDateString(), null, false)
            : null;
    }

    /** A valid 'Y-m-d' string, or null. */
    protected function dateOrNull(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /*
    |--------------------------------------------------------------------------
    | CSN date strip
    |--------------------------------------------------------------------------
    */

    /**
     * The 7 days of the strip with their CSN counts (one grouped query). Every filter and the active tab
     * apply except the CSN date itself, so the other days keep their counts while one is selected.
     *
     * @return list<array{date: string, label: string, count: int, selected: bool, today: bool}>
     */
    public function stripDays(): array
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);

        $counts = $this->modifyQueryWithActiveTab($this->applyFilterBar(ConsignmentNoteResource::getEloquentQuery(), withCsnDate: false))
            ->whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])
            ->toBase()
            ->selectRaw('DATE(issued_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(issued_at)')
            ->pluck('total', 'day');

        $from = $this->dateOrNull($this->csnFrom);
        $to = $this->dateOrNull($this->csnTo);
        $today = today()->toDateString();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $counts, $from, $to, $today): array {
                $date = $start->copy()->addDays($offset);
                $day = $date->toDateString();

                return [
                    'date' => $day,
                    'label' => $date->format('D j M'),
                    'count' => (int) ($counts[$day] ?? 0),
                    // the selected day, or every day inside a CSN date range
                    'selected' => ($from !== null || $to !== null)
                        && ($from === null || $day >= $from)
                        && ($to === null || $day <= $to),
                    'today' => $day === $today,
                ];
            })
            ->all();
    }

    /** Clicking a day shows only that day; clicking the selected day again clears the CSN date. */
    public function selectStripDay(string $day): void
    {
        if (($day = $this->dateOrNull($day)) === null) {
            return;
        }

        $isSelected = $this->csnFrom === $day && $this->csnTo === $day;
        $this->csnFrom = $this->csnTo = $isSelected ? '' : $day;
        $this->syncCsnScope();

        $this->resetPage();
    }

    public function shiftStrip(int $days): void
    {
        $this->stripStart = $this->stripStartDate()->addDays(max(-31, min(31, $days)))->toDateString();
    }

    public function stripToday(): void
    {
        $this->centreStrip();
    }

    public function clearCsnDate(): void
    {
        $this->csnFrom = $this->csnTo = '';
        $this->syncCsnScope();

        $this->resetPage();
    }

    /** e.g. "1 – 7 Oct 2026", "28 Sep – 4 Oct 2026". */
    public function stripRangeLabel(): string
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);

        return match (true) {
            $start->month === $end->month => $start->format('j').' – '.$end->format('j M Y'),
            $start->year === $end->year => $start->format('j M').' – '.$end->format('j M Y'),
            default => $start->format('j M Y').' – '.$end->format('j M Y'),
        };
    }

    public function stripShowsToday(): bool
    {
        return $this->stripShows(today()->toDateString());
    }

    protected function stripShows(string $day): bool
    {
        $start = $this->stripStartDate();

        return $day >= $start->toDateString() && $day <= $start->copy()->addDays(6)->toDateString();
    }

    /** Puts the day (default today) in the middle of the strip. */
    protected function centreStrip(?string $day = null): void
    {
        $anchor = ($day = $this->dateOrNull($day)) !== null ? Carbon::createFromFormat('!Y-m-d', $day) : today();

        $this->stripStart = $anchor->subDays(3)->toDateString();
    }

    protected function stripStartDate(): Carbon
    {
        $start = $this->dateOrNull($this->stripStart);

        return $start !== null ? Carbon::createFromFormat('!Y-m-d', $start) : today()->subDays(3);
    }

    /*
    |--------------------------------------------------------------------------
    | Filter options ('' = All)
    |--------------------------------------------------------------------------
    */

    /** @return array<int|string, string> */
    public function customerOptions(): array
    {
        $query = Customer::query()->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return ['' => 'All'] + $query->pluck('company_name', 'id')->all();
    }

    /** @return array<string, string> */
    public function orderTypeOptions(): array
    {
        return ['' => 'All'] + OrderType::options();
    }

    /** @return array<string, string> */
    public function serviceTypeOptions(): array
    {
        return ['' => 'All'] + ServiceType::options();
    }

    /** @return array<string, string> */
    public function paymentStatusOptions(): array
    {
        return ['' => 'All'] + collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $c) => [$c->value => $c->getLabel()])->all();
    }

    /** @return array<string, string> */
    public function billingTypeOptions(): array
    {
        return ['' => 'All'] + collect(CsnBillingType::cases())->mapWithKeys(fn (CsnBillingType $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<int|string, string> */
    public function saLocationOptions(): array
    {
        return ['' => 'All'] + SaLocation::query()->orderBy('code')->get()
            ->mapWithKeys(fn (SaLocation $l) => [$l->id => $l->csn_prefix.' — '.$l->name])
            ->all();
    }

    /** @return array<int|string, string> */
    public function transferCodeOptions(): array
    {
        return ['' => 'All'] + TransferCode::query()->orderBy('code')->get()
            ->mapWithKeys(fn (TransferCode $t) => [$t->id => filled($t->name) ? $t->code.' — '.$t->name : $t->code])
            ->all();
    }

    /** Salespeople, plus anyone already set as salesperson on a CSN of this company. @return array<int|string, string> */
    public function salespersonOptions(): array
    {
        $used = ConsignmentNoteResource::getEloquentQuery()->whereNotNull('salesperson_id')->select('salesperson_id');

        return ['' => 'All'] + User::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('id', $used)
                ->orWhereHas('roles', fn (Builder $query): Builder => $query->where('name', 'salesperson')))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    public function driverOptions(): array
    {
        return ['' => 'All'] + ConsignmentNoteResource::driverOptions();
    }

    /** @return array<int|string, string> */
    public function lorryOptions(): array
    {
        return ['' => 'All'] + ConsignmentNoteResource::lorryOptions();
    }

    /** Orders that have CSNs in this company (and the selected one, e.g. from an order's "View CSN" link). @return array<int|string, string> */
    public function quotationOptions(): array
    {
        $ids = ConsignmentNoteResource::getEloquentQuery()->whereNotNull('quotation_id')->distinct()->pluck('quotation_id');

        if (ctype_digit($this->quotation)) {
            $ids->push((int) $this->quotation);
        }

        return ['' => 'All'] + Quotation::query()->whereKey($ids->unique()->all())->orderByDesc('id')->get(['id', 'number'])
            ->mapWithKeys(fn (Quotation $q) => [$q->id => $q->number ?: 'Order #'.$q->id])
            ->all();
    }

    /** @return array<string, string> */
    public function yesNoOptions(): array
    {
        return ['' => 'All', '1' => 'Yes', '0' => 'No'];
    }
}
