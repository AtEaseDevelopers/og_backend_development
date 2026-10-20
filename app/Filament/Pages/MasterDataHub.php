<?php

namespace App\Filament\Pages;

use App\Filament\Resources;
use Filament\Pages\Page;

/**
 * Master Data (user, 21 Oct 2026): one menu item; the page shows a card per master data page (with its icon), each
 * opening that page. The master data pages are no longer in the menu themselves.
 */
class MasterDataHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Master Data';

    protected static ?string $title = 'Master Data';

    protected static ?string $slug = 'master-data';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.master-data-hub';

    /** @var array<string, list<array{class: class-string, label: string, description: string, icon: string}>> */
    public const ITEMS = [
        'Customers' => [
            ['class' => Resources\CustomerResource::class, 'label' => 'Customers', 'description' => 'Customers (debtors), their terms, contacts and special prices.', 'icon' => 'heroicon-o-building-storefront'],
            ['class' => Resources\LeadResource::class, 'label' => 'Leads', 'description' => 'Prospects quoted before they become customers.', 'icon' => 'heroicon-o-user-plus'],
            ['class' => Resources\ConsignorConsigneeResource::class, 'label' => 'Consignor / Consignee', 'description' => 'Saved consignors and consignees, tagged to customers.', 'icon' => 'heroicon-o-arrows-right-left'],
        ],
        'Products & pricing' => [
            ['class' => Resources\UomResource::class, 'label' => 'UOM', 'description' => 'Measurement products (size bands) and their prices.', 'icon' => 'heroicon-o-scale'],
            ['class' => Resources\ItemProductResource::class, 'label' => 'Items', 'description' => 'Item products and their prices.', 'icon' => 'heroicon-o-cube'],
            ['class' => Resources\LorryProductResource::class, 'label' => 'Lorries', 'description' => 'Chartered lorry trips and their prices.', 'icon' => 'heroicon-o-truck'],
            ['class' => Resources\DropOffMinChargeResource::class, 'label' => 'Drop-off Min Charges', 'description' => 'Minimum charge per drop-off area.', 'icon' => 'heroicon-o-banknotes'],
            ['class' => Resources\SaLocationResource::class, 'label' => 'SA Locations', 'description' => 'Salesperson areas.', 'icon' => 'heroicon-o-map-pin'],
        ],
        'Fleet & dispatch' => [
            ['class' => Resources\DriverResource::class, 'label' => 'Drivers', 'description' => 'Drivers, their lorry company, lorry and attendant.', 'icon' => 'heroicon-o-user'],
            ['class' => Resources\TransferCodeResource::class, 'label' => 'Transfer Codes', 'description' => 'Transfer codes between branches and their staff.', 'icon' => 'heroicon-o-qr-code'],
            ['class' => Resources\StoreResource::class, 'label' => 'Stores', 'description' => 'O&G stores where customers bring their goods.', 'icon' => 'heroicon-o-home-modern'],
        ],
        'Organisation' => [
            ['class' => Resources\BranchResource::class, 'label' => 'Branches', 'description' => 'Branches and their companies.', 'icon' => 'heroicon-o-building-office-2'],
            ['class' => Resources\UserResource::class, 'label' => 'Staff accounts', 'description' => 'Staff logins, roles and branches.', 'icon' => 'heroicon-o-users'],
            ['class' => SystemSettingsPage::class, 'label' => 'System Settings', 'description' => 'System-wide settings.', 'icon' => 'heroicon-o-adjustments-horizontal'],
        ],
    ];

    /** The cards the user may open (their page's own access rule), by group. */
    public function groups(): array
    {
        return collect(self::ITEMS)
            ->map(fn (array $items) => array_values(array_filter($items, fn (array $item) => static::canOpen($item['class']))))
            ->filter()
            ->all();
    }

    public static function canOpen(string $class): bool
    {
        try {
            return is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::canViewAny() : $class::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function url(string $class): string
    {
        return is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl();
    }
}
