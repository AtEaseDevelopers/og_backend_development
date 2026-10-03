<?php

namespace App\Filament\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\QuotationResource;
use App\Support\PortalEnquiryListingData;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Combined "Orders & CSN" workspace.
 *
 * Tab 1 reuses the Customer Order Review behaviour (inherited from PortalEnquiries:
 * filters, approve / reject / generate quotation, CSV export) in the new list layout.
 * Tab 2 embeds the Consignment Notes table from ConsignmentNoteResource, so every
 * row action (assign lorry, collect payment, PDF, view, edit) keeps working.
 */
class OrderOperations extends PortalEnquiries implements HasTable
{
    use InteractsWithTable;

    public const TAB_ORDERS = 'orders';

    public const TAB_CSN = 'csn';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Orders & CSN';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'filament.pages.order-operations';

    #[Url(as: 'tab', except: self::TAB_ORDERS)]
    public string $activeTab = self::TAB_ORDERS;

    #[Url(as: 'csn', except: 'all')]
    public string $csnScope = 'all';

    public function mount(): void
    {
        if (! $this->filterDateFrom) {
            $this->filterDateFrom = now()->subDays(7)->format('Y-m-d');
        }

        if (! $this->filterDateTo) {
            $this->filterDateTo = now()->format('Y-m-d');
        }

        if (! in_array($this->activeTab, [self::TAB_ORDERS, self::TAB_CSN], true)) {
            $this->activeTab = self::TAB_ORDERS;
        }

        if (! array_key_exists($this->csnScope, $this->csnScopeDefinitions())) {
            $this->csnScope = 'all';
        }
    }

    public function getTitle(): string
    {
        return 'Order Management';
    }

    public function getSubheading(): ?string
    {
        return 'One workspace from customer order review to consignment notes and dispatch.';
    }

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */

    /**
     * Tabs are plain links (full navigation) so the embedded Filament table is always
     * part of the initial render and its Alpine assets load exactly as on a resource page.
     */
    public function getTabUrl(string $tab): string
    {
        return static::getUrl($tab === self::TAB_CSN ? ['tab' => self::TAB_CSN] : []);
    }

    public function isOrdersTab(): bool
    {
        return $this->activeTab === self::TAB_ORDERS;
    }

    /*
    |--------------------------------------------------------------------------
    | Orders tab (Customer Order Review)
    |--------------------------------------------------------------------------
    */

    /** The combined page does not auto-select a row; the detail opens on click. */
    public function applyFilters(): void
    {
        $this->selectedEnquiryId = null;
        $this->showRejectForm = false;
    }

    public function closeDetail(): void
    {
        $this->releaseSelectedLock();
        $this->selectedEnquiryId = null;
        $this->rejectReason = '';
        $this->showRejectForm = false;
    }

    /** Opening a row appends the detail panel below the list and scrolls it into view. */
    public function openDetail(int $enquiryId): void
    {
        parent::openDetail($enquiryId);

        $this->dispatch('ops-scroll-to-detail');
    }

    /** @return array{total: int, needs_attention: int, quoted: int, rejected: int} */
    public function getOrderSummary(): array
    {
        return app(PortalEnquiryListingData::class)->summary([
            'search' => $this->filterSearch,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
        ]);
    }

    /** @return list<array{key: string, label: string, hint: string, count: int, status: string}> */
    public function getOrderCards(): array
    {
        $summary = $this->getOrderSummary();

        return [
            ['key' => 'all', 'label' => 'All', 'hint' => 'Orders in this window', 'count' => $summary['total'], 'status' => 'all'],
            ['key' => 'attention', 'label' => 'Needs attention', 'hint' => 'New enquiries & pricing review', 'count' => $summary['needs_attention'], 'status' => ''],
            ['key' => 'quoted', 'label' => 'Quotation issued', 'hint' => 'Approved · quotation linked', 'count' => $summary['quoted'], 'status' => 'quoted'],
            ['key' => 'rejected', 'label' => 'Rejected', 'hint' => 'Declined at review', 'count' => $summary['rejected'], 'status' => 'rejected'],
        ];
    }

    public function selectOrderCard(string $status): void
    {
        $this->filterStatus = $status === '' ? null : $status;
        $this->applyFilters();
    }

    public function isOrderCardActive(string $status): bool
    {
        $current = $this->filterStatus ?? '';

        return $current === $status;
    }

    /** @return list<array{label: string, color: string}> */
    public function getOrderLegend(): array
    {
        return [
            ['label' => 'Pending review', 'color' => 'gray'],
            ['label' => 'Pricing review', 'color' => 'blue'],
            ['label' => 'Approved / Quoted', 'color' => 'approved'],
            ['label' => 'Rejected', 'color' => 'danger'],
        ];
    }

    public function getCreateOrderUrl(): string
    {
        return QuotationResource::getUrl('create');
    }

    /*
    |--------------------------------------------------------------------------
    | CSN tab (Consignment Notes)
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{label: string, hint: string, card: bool}> */
    private function csnScopeDefinitions(): array
    {
        return [
            'all' => ['label' => 'All', 'hint' => 'Consignment notes', 'card' => true],
            'unassigned' => ['label' => 'Unassigned', 'hint' => 'Pending lorry assignment', 'card' => true],
            'claimed' => ['label' => 'Claimed', 'hint' => 'Claimed · lorry to assign', 'card' => false],
            'assigned' => ['label' => 'Assigned', 'hint' => 'Lorry assigned · check-in pending', 'card' => false],
            'in_transit' => ['label' => 'In transit', 'hint' => 'Delivery in progress', 'card' => true],
            'delivered' => ['label' => 'Delivered', 'hint' => 'Ready for EOD review', 'card' => true],
            'cancelled' => ['label' => 'Cancelled', 'hint' => 'Voided notes', 'card' => false],
        ];
    }

    /** @return list<array{key: string, label: string, hint: string, count: int, card: bool}> */
    public function getCsnScopes(): array
    {
        $scopes = [];

        foreach ($this->csnScopeDefinitions() as $key => $definition) {
            $scopes[] = [
                'key' => $key,
                'label' => $definition['label'],
                'hint' => $definition['hint'],
                'card' => $definition['card'],
                'count' => $this->applyCsnScope(ConsignmentNoteResource::getEloquentQuery(), $key)->count(),
            ];
        }

        return $scopes;
    }

    public function getCsnTotal(): int
    {
        return ConsignmentNoteResource::getEloquentQuery()->count();
    }

    public function setCsnScope(string $scope): void
    {
        if (! array_key_exists($scope, $this->csnScopeDefinitions())) {
            return;
        }

        $this->csnScope = $scope;
        $this->resetPage();
    }

    public function getCreateCsnUrl(): string
    {
        return ConsignmentNoteResource::getUrl('create');
    }

    public function table(Table $table): Table
    {
        return ConsignmentNoteResource::table($table)
            ->query(fn (): Builder => $this->csnQuery())
            // filters open in a scrollable modal from the header row (the dropdown was taller than the viewport)
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filtersFormWidth(\Filament\Support\Enums\MaxWidth::FiveExtraLarge)
            ->filtersTriggerAction(fn (Action $action) => $action->label('Filters')->icon('heroicon-o-funnel')->button()->color('gray'))
            ->recordUrl(fn (ConsignmentNote $record): string => ConsignmentNoteResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No consignment notes')
            ->emptyStateDescription('Nothing matches this scope yet. Convert a confirmed quotation to create the first CSN.');
    }

    protected function configureTableAction(Action $action): void
    {
        if ($action instanceof ViewAction) {
            $action->url(fn (Model $record): string => ConsignmentNoteResource::getUrl('view', ['record' => $record]));
        }

        if ($action instanceof EditAction) {
            $action->url(fn (Model $record): string => ConsignmentNoteResource::getUrl('edit', ['record' => $record]));
        }
    }

    private function csnQuery(): Builder
    {
        $query = ConsignmentNoteResource::getEloquentQuery()
            ->withCount('deliveryOrders')
            ->with(['deliveryOrder.lorry']);

        return $this->applyCsnScope($query, $this->csnScope);
    }

    private function applyCsnScope(Builder $query, string $scope): Builder
    {
        return match ($scope) {
            'unassigned' => $query
                ->whereDoesntHave('deliveryOrder')
                ->whereNull('claimed_by')
                ->whereIn('status', [CsnStatus::PendingAssignment->value, CsnStatus::Confirmed->value, CsnStatus::Draft->value]),
            'claimed' => $query
                ->whereDoesntHave('deliveryOrder')
                ->whereNotNull('claimed_by')
                ->whereIn('status', [CsnStatus::PendingAssignment->value, CsnStatus::Confirmed->value, CsnStatus::Draft->value]),
            'assigned' => $query->where('status', CsnStatus::Assigned->value),
            'in_transit' => $query->where('status', CsnStatus::InTransit->value),
            'delivered' => $query->where('status', CsnStatus::Delivered->value),
            'cancelled' => $query->where('status', CsnStatus::Cancelled->value),
            default => $query,
        };
    }
}
