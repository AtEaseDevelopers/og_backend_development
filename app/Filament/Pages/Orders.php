<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\OrderType;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\OrderListingData;
use App\Support\OrderStage;
use Filament\Pages\Page;
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

    #[Url(as: 'card', except: '')]
    public string $card = '';

    #[Url(as: 'stage', except: '')]
    public string $stage = '';

    #[Url(as: 'customer', except: '')]
    public string $customer = '';

    #[Url(as: 'type', except: '')]
    public string $orderType = '';

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

    #[Url(as: 'valid_from', except: '')]
    public string $validFrom = '';

    #[Url(as: 'valid_until', except: '')]
    public string $validTo = '';

    #[Url(as: 'min', except: '')]
    public string $amountMin = '';

    #[Url(as: 'max', except: '')]
    public string $amountMax = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        $this->filtersOpen = filled($this->paymentStatus) || filled($this->paymentMethod) || filled($this->saLocation)
            || filled($this->pricingSource) || filled($this->quotationStatus) || filled($this->dropOffType)
            || filled($this->createdFrom) || filled($this->createdTo) || filled($this->validFrom) || filled($this->validTo)
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
        return app(OrderListingData::class)->for([
            'search' => $this->search,
            'card' => $this->card ?? '',
            'stage' => $this->stage ?? '',
            'customer_id' => $this->customer,
            'order_type' => $this->orderType,
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
        ]);
    }

    /** @return list<array{key: string, label: string, hint: string, count: int}> */
    public function cards(array $summary): array
    {
        return [
            ['key' => '', 'label' => 'All', 'hint' => 'Orders in this branch', 'count' => $summary['total']],
            ['key' => 'attention', 'label' => 'Needs attention', 'hint' => 'New enquiries & payment review', 'count' => $summary['needs_attention']],
            ['key' => 'customer', 'label' => 'Awaiting customer', 'hint' => 'Quotation confirmation', 'count' => $summary['awaiting_customer']],
            ['key' => 'bill', 'label' => 'Ready to bill', 'hint' => 'Released for billing', 'count' => $summary['ready_to_bill']],
        ];
    }

    public function selectCard(string $key): void
    {
        $this->card = $key;
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    public function resetFilters(): void
    {
        foreach (['search', 'card', 'stage', 'customer', 'orderType', 'salesperson', 'paymentStatus', 'paymentMethod', 'saLocation', 'pricingSource', 'quotationStatus', 'dropOffType', 'createdFrom', 'createdTo', 'validFrom', 'validTo', 'amountMin', 'amountMax'] as $property) {
            $this->{$property} = '';
        }
    }

    /** @return array<string, string> */
    public function legend(): array
    {
        return OrderStage::LEGEND;
    }

    /** @return array<string, string> */
    public function stageOptions(): array
    {
        return OrderStage::stageOptions();
    }

    /** @return array<string, string> */
    public function orderTypeOptions(): array
    {
        return ['' => 'All'] + OrderType::options();
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

    /** Newest customer-submitted enquiry, for the "Order intake" card link. */
    public function latestCustomerOrderUrl(): ?string
    {
        $enquiry = PortalEnquiry::query()
            ->whereIn('source', [PortalEnquiry::SOURCE_PORTAL, PortalEnquiry::SOURCE_SALESPERSON_LINK])
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->latest('id')
            ->first(['id']);

        return $enquiry ? OrderDetail::urlFor('enquiry', $enquiry->id) : null;
    }

    /** @return list<string> */
    public function steps(): array
    {
        return OrderStage::STEPS;
    }
}
