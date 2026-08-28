<?php

namespace App\Providers\Filament;

use App\Domains\MasterData\Models\Company;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SelectBranch;
use App\Http\Controllers\Admin\ConsignmentNotePdfController;
use App\Http\Controllers\Admin\DeliveryOrderPdfController;
use App\Http\Controllers\Admin\InvoicePdfController;
use App\Http\Controllers\Admin\OcrUploadDocumentController;
use App\Http\Controllers\Admin\QuotationPdfController;
use App\Http\Middleware\SyncSelectedBranchFromTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
            ->brandLogo(asset('images/logo-og-circle.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/logo-og-circle.png'))
            ->tenant(Company::class, slugAttribute: 'code')
            ->tenantMenu(false)
            ->homeUrl(fn (): string => route('filament.admin.select-branch'))
            ->colors([
                'primary' => Color::hex('#0f172a'),
                'gray' => Color::Slate,
            ])
            ->sidebarWidth('18rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->globalSearch(false)
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
                Route::get('/consignment-notes/{consignmentNote}/pdf', ConsignmentNotePdfController::class)
                    ->name('consignment-notes.pdf');
                Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)
                    ->name('invoices.pdf');
                Route::get('/delivery-orders/{deliveryOrder}/pdf', DeliveryOrderPdfController::class)
                    ->name('delivery-orders.pdf');
                Route::get('/ocr-uploads/{ocrUpload}/document', OcrUploadDocumentController::class)
                    ->name('ocr-uploads.document');
            })
            ->userMenuItems([
                'change-branch' => MenuItem::make()
                    ->label('Change branch')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn (): string => route('filament.admin.select-branch'))
                    ->sort(-1),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): View => view('filament.hooks.branch-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.sidebar-hover-expand'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.brand-logo-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.quotation-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.consignment-note-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.job-sheet-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.break-bulk-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.delivery-monitoring-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.delivery-task-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.failed-delivery-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.returned-csn-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.missing-csn-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.cash-bill-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.payment-listing-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.cod-listing-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.ocr-quotation-theme'),
            )
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
                SyncSelectedBranchFromTenant::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
