<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The first breadcrumb of a page under Master Data or Reports opens that page (client, 17 Nov 2026: "Master Data"
 * was plain text on most pages). Every list page and menu page of the admin panel is opened.
 */
class BreadcrumbLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_and_reports_crumbs_are_links_on_every_page(): void
    {
        $this->seed();

        $admin = User::query()->role('super_admin')->firstOrFail();
        $company = Company::query()->where('code', 'KL')->firstOrFail();
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $this->actingAs($admin);

        $urls = [];

        foreach ($panel->getResources() as $resource) {
            try {
                $urls[$resource] = $resource::getUrl('index', tenant: $company);
            } catch (\Throwable) {
            }
        }

        foreach ($panel->getPages() as $page) {
            try {
                $urls[$page] = $page::getUrl(tenant: $company);
            } catch (\Throwable) {
            }
        }

        $checked = 0;
        $problems = [];

        foreach ($urls as $class => $url) {
            $response = $this->get($url);

            if ($response->status() !== 200) {
                continue; // a page this user may not open, or one that needs a record
            }

            if (! preg_match('/<nav[^>]*og-page-breadcrumbs.*?<\/nav>/s', $response->getContent(), $nav) || ! preg_match('/<li.*?<\/li>/s', $nav[0], $first)) {
                continue;
            }

            $label = trim(strip_tags($first[0]));
            $isHub = in_array($class, [\App\Filament\Pages\MasterDataHub::class, \App\Filament\Pages\ReportsHub::class], true);

            if (in_array($label, ['Master Data', 'Reports'], true) && ! $isHub) {
                $checked++;

                if (! str_contains($first[0], '<a')) {
                    $problems[] = class_basename($class);
                }
            }
        }

        $this->assertSame([], $problems, 'pages whose first breadcrumb is not a link');
        $this->assertGreaterThan(15, $checked);
        fwrite(STDERR, "\nbreadcrumb pages checked: $checked of ".count($urls)."\n");
    }
}
