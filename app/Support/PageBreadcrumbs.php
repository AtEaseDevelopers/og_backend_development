<?php

namespace App\Support;

use App\Filament\Pages\CreateOrder;
use App\Filament\Pages\CreateQuotation;
use App\Filament\Pages\EditOrder;
use App\Filament\Pages\MasterDataHub;
use App\Filament\Pages\OrderDetail;
use App\Filament\Pages\Orders;
use App\Filament\Pages\ReportDetail;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\SystemSettingsPage;
use App\Filament\Resources\QuoteResource;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Throwable;

/**
 * The path shown at the top of every admin page (user, 4 Nov 2026: the customer page and Create quotation had none):
 * the menu group, the resource or parent page, then the page — "Master Data › Customers › List",
 * "Operations › Quotations › Create quotation". Rendered by the page-breadcrumbs hook for every page, so a page
 * without a heading (whose Filament header is not drawn) has it too; the panel's own header breadcrumbs are off.
 */
class PageBreadcrumbs
{
    /** custom pages opened from another page: that page is the parent crumb */
    private const PARENTS = [
        CreateQuotation::class => [QuoteResource::class, 'Quotations'],
        CreateOrder::class => [Orders::class, 'Orders'],
        EditOrder::class => [Orders::class, 'Orders'],
        OrderDetail::class => [Orders::class, 'Orders'],
        ReportDetail::class => [ReportsHub::class, 'Reports'],
        SystemSettingsPage::class => [MasterDataHub::class, 'Master Data'],
    ];

    /** @return array<int|string, string> a plain crumb has an int key, a link its URL */
    public static function for(Page $page): array
    {
        try {
            if (! Filament::getTenant()) {
                return [];
            }

            $crumbs = $page->getBreadcrumbs();

            if ($crumbs === []) {
                $crumbs = static::defaults($page);
            }

            $group = $page instanceof ResourcePage ? $page::getResource()::getNavigationGroup() : $page::getNavigationGroup();
            // a page opened from another (Create quotation, Order …) sits in its parent's menu group
            $group = $group ?? (isset(self::PARENTS[$page::class]) ? self::PARENTS[$page::class][0]::getNavigationGroup() : null);
            $group = filled($group) ? (string) $group : null;
            $first = $crumbs === [] ? null : reset($crumbs);

            if ($group !== null && $first !== $group) {
                $crumbs = [$group] + static::reindex($crumbs);
            }

            return static::reindex($crumbs);
        } catch (Throwable) {
            return [];
        }
    }

    /** a custom page: its parent page (when it has one) then its title */
    private static function defaults(Page $page): array
    {
        $crumbs = [];
        $parent = self::PARENTS[$page::class] ?? null;

        if ($parent) {
            $crumbs[$parent[0]::getUrl()] = $parent[1];
        }

        // the dashboard's title carries the branch ("Dashboard — KL"): the crumb is just its menu label
        $title = $page instanceof \App\Filament\Pages\Dashboard ? (string) $page::getNavigationLabel() : (string) $page->getTitle();

        if ($title !== '' && end($crumbs) !== $title) {
            $crumbs[] = $title;
        }

        return $crumbs;
    }

    /** plain crumbs get fresh int keys so that `[$group] + $crumbs` never overwrites one */
    private static function reindex(array $crumbs): array
    {
        $out = [];
        $n = 100;

        foreach ($crumbs as $url => $label) {
            $out[is_int($url) ? $n++ : $url] = $label;
        }

        return $out;
    }
}
