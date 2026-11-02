<?php

namespace App\Filament\Concerns;

use App\Filament\Pages\MasterDataHub;

/**
 * The pages of a master data resource start their breadcrumbs at Master Data (user, 3 Nov 2026):
 * Master Data › Customers › List.
 */
trait MasterDataBreadcrumbs
{
    public function getBreadcrumbs(): array
    {
        $crumbs = parent::getBreadcrumbs();

        if ($crumbs === []) {
            return [];
        }

        try {
            return [MasterDataHub::getUrl() => 'Master Data'] + $crumbs;
        } catch (\Throwable) {
            return $crumbs;
        }
    }
}
