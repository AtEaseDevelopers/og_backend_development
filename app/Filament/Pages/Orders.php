<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\SaLocation;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Filament\Resources\QuotationResource;
use App\Models\User;
use App\Support\OrderListingData;
use Livewire\Attributes\Url;

/**
 * Orders workspace: one list following the flow
 * Enquiry → Quotation → Confirmation → Proforma → Payment / Release → Invoice / Cash Bill → CSN.
 *
 * Enquiry rows open the review panel below the list (approve / reject / prepare quotation);
 * order rows link to the order record, and converted orders link to their CSNs.
 * Inherits the enquiry review behaviour (lock, heartbeat, actions) from PortalEnquiries.
 */
class Orders extends PortalEnquiries
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'orders';

    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'filament.pages.orders';

    #[Url(as: 'stage', except: '')]
    public ?string $filterStatus = null;

    #[Url(as: 'type', except: '')]
    public ?string $filterOrderType = null;

    #[Url(as: 'salesperson', except: '')]
    public ?string $filterSalesperson = null;

    #[Url(as: 'sa', except: '')]
    public ?string $filterSaLocation = null;

    #[Url(as: 'billing', except: '')]
    public ?string $filterBilling = null;

    public function mount(): void
    {
        if (! $this->filterDateFrom) {
            $this->filterDateFrom = now()->subDays(30)->format('Y-m-d');
        }

        if (! $this->filterDateTo) {
            $this->filterDateTo = now()->format('Y-m-d');
        }
    }

    public function getTitle(): string
    {
        return 'Orders';
    }

    public function getSubheading(): ?string
    {
        return 'Enquiry → Quotation → Confirmation → Proforma → Payment / Admin release → Invoice / Cash Bill → CSN';
    }

    /** No auto-selection: the enquiry panel opens on click. */
    public function applyFilters(): void
    {
        $this->releaseSelectedLock();
        $this->selectedEnquiryId = null;
        $this->showRejectForm = false;
    }

    public function resetFilters(): void
    {
        $this->filterSearch = null;
        $this->filterStatus = null;
        $this->filterOrderType = null;
        $this->filterSalesperson = null;
        $this->filterSaLocation = null;
        $this->filterBilling = null;
        $this->filterDateFrom = now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
        $this->applyFilters();
    }

    public function closeDetail(): void
    {
        $this->releaseSelectedLock();
        $this->selectedEnquiryId = null;
        $this->rejectReason = '';
        $this->showRejectForm = false;
    }

    public function openDetail(int $enquiryId): void
    {
        parent::openDetail($enquiryId);

        $this->dispatch('ops-scroll-to-detail');
    }

    /** @return array<string, mixed> */
    public function getOrders(): array
    {
        return app(OrderListingData::class)->for([
            'search' => $this->filterSearch,
            'stage' => $this->filterStatus ?? '',
            'order_type' => $this->filterOrderType,
            'salesperson_id' => $this->filterSalesperson,
            'sa_location_id' => $this->filterSaLocation,
            'billing_status' => $this->filterBilling,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
        ]);
    }

    /** @return list<array{key: string, label: string, hint: string, count: int, stage: string}> */
    public function getCards(array $summary): array
    {
        return [
            ['key' => 'all', 'label' => 'All', 'hint' => 'Open orders in this window', 'count' => $summary['total'], 'stage' => ''],
            ['key' => 'attention', 'label' => 'Needs attention', 'hint' => 'New enquiries, pricing & credit review', 'count' => $summary['needs_attention'], 'stage' => 'enquiry'],
            ['key' => 'customer', 'label' => 'Awaiting customer', 'hint' => 'Quotation sent · not confirmed', 'count' => $summary['awaiting_customer'], 'stage' => 'awaiting_customer'],
            ['key' => 'bill', 'label' => 'Ready to bill', 'hint' => 'Confirmed · payment or release pending', 'count' => $summary['ready_to_bill'], 'stage' => 'payment'],
        ];
    }

    public function selectCard(string $stage): void
    {
        $this->filterStatus = $stage === '' ? null : $stage;
        $this->applyFilters();
    }

    public function isCardActive(string $stage): bool
    {
        return ($this->filterStatus ?? '') === $stage;
    }

    /** @return array<string, string> */
    public function stageOptions(): array
    {
        return OrderListingData::stageOptions();
    }

    /** @return array<string, string> */
    public function orderTypeOptions(): array
    {
        return ['' => 'All types'] + OrderType::options();
    }

    /** @return array<string, string> */
    public function billingOptions(): array
    {
        return ['' => 'Any payment state'] + collect(BillingStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])->all();
    }

    /** @return array<int|string, string> */
    public function salespersonFilterOptions(): array
    {
        return ['' => 'All salespersons'] + User::query()->role('salesperson')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    public function saLocationOptions(): array
    {
        return ['' => 'All SA locations'] + SaLocation::query()->orderBy('code')->get()->mapWithKeys(fn (SaLocation $l) => [$l->id => $l->code.' — '.$l->name])->all();
    }

    public function getCreateOrderUrl(): string
    {
        return QuotationResource::getUrl('create');
    }

    /** @return list<string> */
    public function steps(): array
    {
        return OrderListingData::STEPS;
    }
}
