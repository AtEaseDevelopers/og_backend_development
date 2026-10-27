<?php

namespace App\Filament\Pages;

use App\Support\CodListingData;
use Filament\Pages\Page;

class CodListing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'COD Listing';

    // opened from its card on the Reports page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

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
        // no text under the page title (user, 28 Oct 2026)
        return null;
    }

    public function applyFilters(): void
    {
        // Re-render with current filter state.
    }

    /** The list is always for one day: clearing the date picker goes back to today. */
    public function updatedFilterDate(): void
    {
        if (blank($this->filterDate)) {
            $this->filterDate = now()->format('Y-m-d');
        }
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
