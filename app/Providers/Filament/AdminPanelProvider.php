<?php

namespace App\Providers\Filament;

use App\Domains\MasterData\Models\Company;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\SelectBranch;
use App\Http\Controllers\Admin\QuotationPdfController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('O&G Transport')
            ->tenant(Company::class, slugAttribute: 'code')
            ->tenantMenu(false)
            ->homeUrl(fn (): string => route('filament.admin.select-branch'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarWidth('18rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->navigationGroups([
                'Operations',
                'Dispatch',
                'Delivery',
                'Billing',
                'Commission',
                'Approvals',
                'Integrations',
                'Reports',
                'Fleet',
                'Master Data',
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/select-branch', SelectBranch::class)->name('select-branch');
            })
            ->authenticatedTenantRoutes(function (): void {
                Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)
                    ->name('quotations.pdf');
            })
            ->userMenuItems([
                'change-branch' => MenuItem::make()
                    ->label('Change branch')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn (): string => route('filament.admin.select-branch'))
                    ->sort(-1),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SyncSelectedBranchFromTenant::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
