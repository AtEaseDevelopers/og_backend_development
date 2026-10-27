<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Enums\OrderType;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\OrderListingData;
use App\Support\OrderStage;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\QuotationStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Throwable;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;

/**
 * Order management: one workspace from enquiry to billing and dispatch.
 *
 * Every row is an enquiry waiting for pricing or an order record (one per consignor–consignee,
 * sharing the enquiry's order number). Rows open the order detail page.
 */
class Orders extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'orders';

    protected static string $view = 'filament.pages.orders';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Order numbers pinned as tags in the search box (user, 27 Oct 2026): the list shows orders matching any of them. @var list<string> */
    public array $searchTags = [];

    #[Url(as: 'card', except: '')]
    public string $card = '';

    #[Url(as: 'stage', except: '')]
    public string $stage = '';

    #[Url(as: 'customer', except: '')]
    public string $customer = '';

    #[Url(as: 'ctype', except: '')]
    public string $customerType = '';

    #[Url(as: 'type', except: '')]
    public string $orderType = '';

    #[Url(as: 'service', except: '')]
    public string $serviceType = '';

    #[Url(as: 'salesperson', except: '')]
    public string $salesperson = '';

    #[Url(as: 'payment', except: '')]
    public string $paymentStatus = '';

    #[Url(as: 'method', except: '')]
    public string $paymentMethod = '';

    #[Url(as: 'sa', except: '')]
    public string $saLocation = '';

    #[Url(as: 'pricing', except: '')]
    public string $pricingSource = '';

    #[Url(as: 'qs', except: '')]
    public string $quotationStatus = '';

    #[Url(as: 'dropoff', except: '')]
    public string $dropOffType = '';

    #[Url(as: 'from', except: '')]
    public string $createdFrom = '';

    #[Url(as: 'until', except: '')]
    public string $createdTo = '';

    /** Excel-style column filters: field => the values left ticked (absent = every value). */
    #[Url(as: 'cf', except: [])]
    public array $colFilters = [];

    /** Columns hidden with the column toggle (kept for the session). */
    #[Session(key: 'orders.hidden-columns')]
    public array $hiddenColumns = [];

    /** First day (Y-m-d) of the 7-day created-date strip. */
    public string $stripStart = '';

    #[Url(as: 'valid_from', except: '')]
    public string $validFrom = '';

    #[Url(as: 'valid_until', except: '')]
    public string $validTo = '';

    #[Url(as: 'min', except: '')]
    public string $amountMin = '';

    #[Url(as: 'max', except: '')]
    public string $amountMax = '';

    /** Table header sort: one of OrderListingData::SORTS, '' = newest first. */
    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $dir = 'asc';

    public bool $filtersOpen = false;

    /**
     * Bulk order processing (user, 28 Oct 2026): the orders at Pending item (a Store order paid and billed, waiting
     * for its goods) verified, their CSNs created and put on one lorry in one go. With a transfer code chosen each
     * CSN gets a subsheet with that code and the subsheet goes on the lorry; without one the CSN itself goes.
     */
    public function bulkProcessAction(): Action
    {
        return Action::make('bulkProcess')
            ->label('Bulk order processing')
            ->icon('heroicon-o-truck')
            ->color('gray')
            ->slideOver()
            ->modalWidth('2xl')
            ->modalHeading('Bulk order processing')
            ->form([
                Forms\Components\Select::make('lorry_id')
                    ->label('Lorry')
                    ->placeholder('Select lorry')
                    ->options(fn () => ConsignmentNoteResource::lorryOptions())
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('transfer_code')
                    ->label('Transfer code')
                    ->placeholder('None · the CSN goes on the lorry')
                    ->options(fn () => $this->transferCodeOptions())
                    ->searchable(),
                Forms\Components\CheckboxList::make('order_ids')
                    ->label(fn (Forms\Get $get) => 'Orders at Pending item ('.count((array) $get('order_ids')).' ticked)')
                    ->options(fn () => $this->pendingItemOrders()->mapWithKeys(fn (Quotation $o) => [$o->id => $o->orderNumber()])->all())
                    ->descriptions(fn () => $this->pendingItemOrders()->mapWithKeys(fn (Quotation $o) => [$o->id => collect([
                        $o->customer?->company_name,
                        $o->consignee_name,
                        $o->created_at?->format('d/m/Y'),
                    ])->filter()->implode(' · ')])->all())
                    ->bulkToggleable()
                    ->live()
                    ->required()
                    ->validationMessages(['required' => 'Tick at least one order.']),
            ])
            ->modalSubmitActionLabel('Verify, create CSN & assign to lorry')
            ->action(function (array $data): void {
                $orders = $this->pendingItemOrders()->whereIn('id', array_map('intval', (array) ($data['order_ids'] ?? [])));

                if ($orders->isEmpty()) {
                    Notification::make()->title('No order ticked')->warning()->send();

                    return;
                }

                [$csns, $subsheets, $done, $failed] = [collect(), collect(), [], []];
                $code = trim((string) ($data['transfer_code'] ?? ''));

                foreach ($orders as $order) {
                    try {
                        $created = app(\App\Domains\Quotation\Actions\MarkStoreItemReady::class)->execute($order, auth()->user());

                        foreach ($created as $csn) {
                            $csn = $csn->fresh(['subsheets', 'deliveryOrder', 'sourceBranch']);

                            // a transfer code chosen: a subsheet with that code goes on the lorry
                            if ($code !== '') {
                                app(\App\Domains\Dispatch\Actions\CreateSubsheet::class)->forCsn($csn, [
                                    'transfer_code' => $code,
                                    'task_type' => 'transfer',
                                    'notes' => 'Bulk order processing',
                                ]);
                                $csn = $csn->fresh(['subsheets', 'deliveryOrder', 'sourceBranch']);
                            }

                            $csn->subsheets->isNotEmpty() ? $subsheets = $subsheets->merge($csn->subsheets) : $csns->push($csn);
                            $done[] = $csn->number;
                        }
                    } catch (Throwable $e) {
                        $failed[] = $order->orderNumber().' ('.$e->getMessage().')';
                    }
                }

                if ($csns->isNotEmpty() || $subsheets->isNotEmpty()) {
                    ConsignmentNoteResource::assignSelection($csns, $subsheets->values(), ['lorry_id' => $data['lorry_id']]);
                }

                $notice = Notification::make()
                    ->title(count($done).' order(s) processed'.($done ? ': CSN '.implode(', ', $done) : ''))
                    ->body($failed ? 'Not processed: '.implode('; ', $failed) : null);
                ($failed ? $notice->warning() : $notice->success())->send();
            });
    }

    /** The orders at Pending item of this company, newest first. */
    public function pendingItemOrders(): Collection
    {
        return Quotation::query()
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->where('status', QuotationStatus::Confirmed)
            ->where('service_type', ServiceType::Store)
            ->whereNull('item_received_at')
            ->whereDoesntHave('consignmentNotes')
            ->with('customer:id,company_name')
            ->latest('id')
            ->limit(300)
            ->get()
            ->filter(fn (Quotation $order) => $order->isBilledAwaitingItem())
            ->values();
    }

    /** The active transfer codes, "A — From Johor … to KL". @return array<string, string> */
    public function transferCodeOptions(): array
    {
        return TransferCode::query()->where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (TransferCode $c) => [$c->code => filled($c->name) ? $c->code.' — '.$c->name : $c->code])->all();
    }

    public function mount(): void
    {
        // Old links may carry a stage that no longer has a tag ('all', removed keys): show All instead
        if (! array_key_exists($this->stage, OrderStage::STAGES)) {
            $this->stage = '';
        }

        if (! in_array($this->sort, OrderListingData::SORTS, true)) {
            $this->sort = '';
        }

        $this->dir = $this->dir === 'desc' ? 'desc' : 'asc';
        $this->colFilters = $this->cleanColumnFilters($this->colFilters);
        $this->centreStrip($this->createdFrom ?: $this->createdTo);

        // Only the "Filters +" panel fields; the date ranges sit next to the search and are always shown
        $this->filtersOpen = filled($this->paymentStatus) || filled($this->paymentMethod) || filled($this->saLocation)
            || filled($this->pricingSource) || filled($this->quotationStatus) || filled($this->dropOffType)
            || filled($this->amountMin) || filled($this->amountMax);
    }

    public function getTitle(): string
    {
        return 'Order management';
    }

    /** The page renders its own header (breadcrumb, title, actions) to match the design. */
    public function getHeading(): string
    {
        return '';
    }

    /** @return array<string, mixed> */
    public function getOrders(): array
    {
        return app(OrderListingData::class)->for($this->listingFilters());
    }

    /** @return array<string, mixed> */
    protected function listingFilters(): array
    {
        return [
            'search' => $this->search,
            'search_tags' => $this->searchTags,
            'card' => $this->card ?? '',
            'stage' => $this->stage ?? '',
            'customer_id' => $this->customer,
            'customer_type' => $this->customerType,
            'order_type' => $this->orderType,
            'service_type' => $this->serviceType,
            'salesperson_id' => $this->salesperson,
            'payment_status' => $this->paymentStatus,
            'payment_method' => $this->paymentMethod,
            'sa_location_id' => $this->saLocation,
            'pricing_source' => $this->pricingSource,
            'quotation_status' => $this->quotationStatus,
            'drop_off_type' => $this->dropOffType,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'valid_from' => $this->validFrom,
            'valid_to' => $this->validTo,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'column_filters' => $this->colFilters,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Excel-style column filters
    |--------------------------------------------------------------------------
    */

    /**
     * OK in a column's filter: the ticked values of each of its fields. A field with every value
     * ticked (null) is not filtered.
     *
     * @param  array<string, list<string>|null>  $selected
     */
    public function applyColumnFilter(string $column, array $selected): void
    {
        foreach (array_keys(OrderListingData::COLUMN_FILTERS[$column] ?? []) as $field) {
            $values = $selected[$field] ?? null;

            if (is_array($values)) {
                $this->colFilters[$field] = array_values(array_unique(array_map('strval', $values)));
            } else {
                unset($this->colFilters[$field]);
            }
        }
    }

    public function clearColumnFilter(string $column): void
    {
        foreach (array_keys(OrderListingData::COLUMN_FILTERS[$column] ?? []) as $field) {
            unset($this->colFilters[$field]);
        }
    }

    /** Sort A to Z / Z to A from a column's filter menu. */
    public function sortColumn(string $key, string $dir): void
    {
        if (in_array($key, OrderListingData::SORTS, true)) {
            [$this->sort, $this->dir] = [$key, $dir === 'desc' ? 'desc' : 'asc'];
        }
    }

    public function columnFiltered(string $column): bool
    {
        return array_intersect(array_keys(OrderListingData::COLUMN_FILTERS[$column] ?? []), array_keys($this->colFilters)) !== []
            || ($column === 'amount' && (filled($this->amountMin) || filled($this->amountMax)))
            || ($column === 'date' && (filled($this->createdFrom) || filled($this->createdTo)));
    }

    /** @return array<string, list<string>> */
    protected function cleanColumnFilters(array $filters): array
    {
        $fields = array_merge(...array_values(array_map('array_keys', OrderListingData::COLUMN_FILTERS)));

        return collect($filters)
            ->filter(fn ($values, $field) => in_array($field, $fields, true) && is_array($values))
            ->map(fn (array $values) => array_values(array_map('strval', $values)))
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Created-date strip (same as the CSN date strip on the CSN list)
    |--------------------------------------------------------------------------
    */

    /**
     * The 7 days of the strip with how many orders were created each day. Every other filter applies,
     * the created date itself does not, so the other days keep their counts while one is selected.
     *
     * @return list<array{date: string, label: string, count: int, selected: bool, today: bool}>
     */
    public function stripDays(): array
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);

        $counts = collect(app(OrderListingData::class)->for(array_merge($this->listingFilters(), [
            'created_from' => $start->toDateString(),
            'created_to' => $end->toDateString(),
        ]))['rows'])->countBy(fn (array $row) => $row['created_at']?->toDateString());

        $from = $this->dateOrNull($this->createdFrom);
        $to = $this->dateOrNull($this->createdTo);
        $today = today()->toDateString();

        return collect(range(0, 6))->map(function (int $offset) use ($start, $counts, $from, $to, $today): array {
            $date = $start->copy()->addDays($offset);
            $day = $date->toDateString();

            return [
                'date' => $day,
                'label' => $date->format('D j M'),
                'count' => (int) ($counts[$day] ?? 0),
                'selected' => ($from !== null || $to !== null) && ($from === null || $day >= $from) && ($to === null || $day <= $to),
                'today' => $day === $today,
            ];
        })->all();
    }

    /** Clicking a day shows only orders created that day; clicking it again clears the created date. */
    public function selectStripDay(string $day): void
    {
        if (($day = $this->dateOrNull($day)) === null) {
            return;
        }

        $isSelected = $this->createdFrom === $day && $this->createdTo === $day;
        $this->createdFrom = $this->createdTo = $isSelected ? '' : $day;
    }

    public function shiftStrip(int $days): void
    {
        $this->stripStart = $this->stripStartDate()->addDays(max(-31, min(31, $days)))->toDateString();
    }

    public function stripToday(): void
    {
        $this->centreStrip();
    }

    public function clearCreatedDate(): void
    {
        $this->createdFrom = $this->createdTo = '';
    }

    /** e.g. "7 – 13 Oct 2026", "28 Sep – 4 Oct 2026". */
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

    /** A created date picked on the calendar brings that day into the strip. */
    public function updatedCreatedFrom(): void
    {
        $day = $this->dateOrNull($this->createdFrom);

        if ($day !== null && ! $this->stripShows($day)) {
            $this->centreStrip($day);
        }
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

    protected function dateOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * Table columns: header label and sort key (filtering lives in the filter card above the table).
     *
     * @return list<array{key: string, label: string, num: bool}>
     */
    public function columns(): array
    {
        return [
            ['key' => 'order', 'label' => 'Order / Customer', 'num' => false],
            ['key' => 'date', 'label' => 'Order date', 'num' => false],
            ['key' => 'route', 'label' => 'Route', 'num' => false],
            ['key' => 'salesperson', 'label' => 'Salesperson', 'num' => false],
            ['key' => 'service', 'label' => 'Service', 'num' => false],
            ['key' => 'stage', 'label' => 'Order stage', 'num' => false],
            ['key' => 'payment', 'label' => 'Payment', 'num' => false],
            ['key' => 'amount', 'label' => 'Amount', 'num' => true],
            ['key' => 'next', 'label' => 'Next step', 'num' => false],
        ];
    }

    /** The columns shown (the column toggle hides the others; Order / Customer always stays). */
    public function visibleColumns(): array
    {
        return array_values(array_filter($this->columns(), fn (array $column) => $column['key'] === 'order' || ! in_array($column['key'], $this->hiddenColumns, true)));
    }

    public function toggleColumn(string $key): void
    {
        if ($key === 'order' || ! in_array($key, array_column($this->columns(), 'key'), true)) {
            return;
        }

        $this->hiddenColumns = in_array($key, $this->hiddenColumns, true)
            ? array_values(array_diff($this->hiddenColumns, [$key]))
            : [...$this->hiddenColumns, $key];
    }

    public function showAllColumns(): void
    {
        $this->hiddenColumns = [];
    }

    /** Header click: ascending, then descending, then back to the default (newest first). */
    public function sortBy(string $key): void
    {
        if (! in_array($key, OrderListingData::SORTS, true)) {
            return;
        }

        if ($this->sort !== $key) {
            [$this->sort, $this->dir] = [$key, 'asc'];
        } elseif ($this->dir === 'asc') {
            $this->dir = 'desc';
        } else {
            [$this->sort, $this->dir] = ['', 'asc'];
        }
    }

    /** @return list<array{key: string, label: string, hint: string, count: int}> */
    public function cards(array $summary): array
    {
        return [
            ['key' => '', 'label' => 'All', 'hint' => 'Orders in this company', 'count' => $summary['total']],
            ['key' => 'attention', 'label' => 'Needs attention', 'hint' => 'New enquiries & payment review', 'count' => $summary['needs_attention']],
            ['key' => 'customer', 'label' => 'Awaiting customer', 'hint' => 'Quotation confirmation', 'count' => $summary['awaiting_customer']],
            ['key' => 'bill', 'label' => 'Ready to bill', 'hint' => 'Released for billing', 'count' => $summary['ready_to_bill']],
        ];
    }

    public function selectCard(string $key): void
    {
        $this->card = $key;
    }

    public function selectStage(string $key): void
    {
        $this->stage = array_key_exists($key, OrderStage::STAGES) ? $key : '';
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    /** Enter in the search box: the text becomes a tag (searched together with the others) and the box clears. */
    public function addSearchTag(): void
    {
        $tag = trim($this->search);

        if ($tag !== '' && ! in_array($tag, $this->searchTags, true)) {
            $this->searchTags[] = $tag;
        }

        $this->search = '';
    }

    public function removeSearchTag(int $index): void
    {
        unset($this->searchTags[$index]);
        $this->searchTags = array_values($this->searchTags);
    }

    public function resetFilters(): void
    {
        foreach (['search', 'card', 'stage', 'customer', 'customerType', 'orderType', 'serviceType', 'salesperson', 'paymentStatus', 'paymentMethod', 'saLocation', 'pricingSource', 'quotationStatus', 'dropOffType', 'createdFrom', 'createdTo', 'validFrom', 'validTo', 'amountMin', 'amountMax', 'sort'] as $property) {
            $this->{$property} = '';
        }

        $this->searchTags = [];
        $this->dir = 'asc';
        $this->colFilters = [];
        $this->centreStrip();
    }

    /**
     * "All" (every open order) first, then one tag per order stage with its count.
     *
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, color: string, count: int}>
     */
    public function stageTags(array $counts): array
    {
        $tags = [['key' => '', 'label' => 'All', 'color' => 'all', 'count' => (int) ($counts[''] ?? 0)]];

        foreach (OrderStage::STAGES as $key => $stage) {
            $tags[] = ['key' => $key, 'label' => $stage['label'], 'color' => $stage['color'], 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $tags;
    }

    /** @return array<string, string> */
    public function customerTypeOptions(): array
    {
        return OrderListingData::customerTypeOptions();
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
        return OrderStage::paymentOptions();
    }

    /** @return array<string, string> */
    public function paymentMethodOptions(): array
    {
        return OrderListingData::paymentMethodOptions();
    }

    /** @return array<string, string> */
    public function pricingSourceOptions(): array
    {
        return OrderListingData::pricingSourceOptions();
    }

    /** @return array<string, string> */
    public function quotationStatusOptions(): array
    {
        return OrderListingData::quotationStatusOptions();
    }

    /** @return array<string, string> */
    public function dropOffTypeOptions(): array
    {
        return OrderListingData::dropOffTypeOptions();
    }

    /** @return array<int|string, string> */
    public function customerOptions(): array
    {
        $query = Customer::query()->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return ['' => 'All'] + $query->pluck('company_name', 'id')->all();
    }

    /** @return array<int|string, string> */
    public function salespersonOptions(): array
    {
        return ['' => 'All'] + User::query()->role('salesperson')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    public function saLocationOptions(): array
    {
        return ['' => 'All'] + SaLocation::query()->orderBy('code')->get()->mapWithKeys(fn (SaLocation $l) => [$l->id => $l->code])->all();
    }

    public function createUrl(): string
    {
        return CreateOrder::getUrl();
    }

    /** @return list<string> */
    public function steps(): array
    {
        return OrderStage::STEPS;
    }

    /**
     * "Ready for CSN" on a Store order's row (user, 21 Oct 2026): its goods are at the store, so its CSN is created
     * and the order moves to CSN created.
     */
    public function readyForCsnAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('readyForCsn')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-archive-box-arrow-down')
            ->modalHeading(fn (array $arguments) => 'Goods of '.(\App\Domains\Quotation\Models\Quotation::query()->find($arguments['order'] ?? 0)?->orderNumber() ?? 'this order').' at the store?')
            ->modalDescription('Confirm the customer\'s goods are at the store and ready to send: the CSN is created and the order moves to CSN created.')
            ->modalSubmitActionLabel('Ready for CSN')
            ->action(function (array $arguments): void {
                $order = \App\Domains\Quotation\Models\Quotation::query()->find($arguments['order'] ?? 0);

                if (! $order || (CurrentCompany::id() && (int) $order->company_id !== (int) CurrentCompany::id())) {
                    \Filament\Notifications\Notification::make()->title('Order not found')->danger()->send();

                    return;
                }

                try {
                    $csns = app(\App\Domains\Quotation\Actions\MarkStoreItemReady::class)->execute($order, auth()->user());
                    \Filament\Notifications\Notification::make()
                        ->title('Order '.$order->orderNumber().' ready · CSN created')
                        ->body($csns->pluck('number')->implode(', ') ?: null)
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    \Filament\Notifications\Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
