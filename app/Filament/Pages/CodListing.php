<?php

namespace App\Filament\Pages;

use App\Support\CodListingData;
use Filament\Pages\Page;

class CodListing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'COD Listing';

    protected static ?int $navigationSort = 22;

    protected static string $view = 'filament.pages.cod-listing';

    public ?string $filterDate = null;

    public ?string $filterSearch = null;

    public ?string $filterStatus = null;

    public function mount(): void
    {
        $this->filterDate = now()->format('Y-m-d');
    }

    public function getTitle(): string
    {
        return 'COD Listing';
    }

    public function getSubheading(): ?string
    {
        return 'View today\'s working drivers and their assigned COD collection totals.';
    }

    public function applyFilters(): void
    {
        // Re-render with current filter state.
    }

    public function resetFilters(): void
    {
        $this->filterDate = now()->format('Y-m-d');
        $this->filterSearch = null;
        $this->filterStatus = null;
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return CodListingData::statusFilterOptions();
    }

    /** @return array<string, mixed> */
    public function getListingData(): array
    {
        return app(CodListingData::class)->for([
            'date' => $this->filterDate,
            'search' => $this->filterSearch,
            'status' => $this->filterStatus,
        ]);
    }
}
