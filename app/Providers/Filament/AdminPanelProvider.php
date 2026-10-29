<?php

namespace App\Providers\Filament;

use App\Domains\MasterData\Models\Company;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
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
            ->homeUrl(fn (): string => \App\Support\DefaultBranch::homeUrl())
            ->colors([
                'primary' => Color::hex('#0f172a'),
                'gray' => Color::Slate,
            ])
            ->sidebarWidth('21rem')
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
                // The branch chooser page is gone: this old URL goes straight to the user's branch dashboard
                Route::get('/select-branch', \App\Http\Controllers\Admin\RedirectToSelectBranchController::class)->name('select-branch');
            })
            ->authenticatedTenantRoutes(function (): void {
                Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)
                    ->name('quotations.pdf');
                // the Quotation pages (QuoteResource, own table, 20 Oct 2026) use /quotations; the order PDF route above stays
                Route::get('/quotes/{quote}/pdf', \App\Http\Controllers\Admin\QuotePdfController::class)
                    ->name('quotes.pdf');
                // Purchase Order (PO) PDF in the client's layout (user, 27 Oct 2026)
                Route::get('/commission-slips/{slip}/pdf', \App\Http\Controllers\Admin\CommissionPoPdfController::class)
                    ->name('commission-slips.pdf');
                // Job Sheet print in the client's layout, a page per job sheet code (user, 27 Oct 2026)
                Route::get('/job-sheets/{jobSheet}/pdf', \App\Http\Controllers\Admin\JobSheetPdfController::class)
                    ->name('job-sheets.pdf');
                Route::get('/new-portal-orders', \App\Http\Controllers\Admin\NewPortalOrdersController::class)
                    ->name('new-portal-orders');
                Route::get('/consignment-notes/{consignmentNote}/pdf', ConsignmentNotePdfController::class)
                    ->name('consignment-notes.pdf');
                Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)
                    ->name('invoices.pdf');
                Route::get('/proforma-invoices/{proformaInvoice}/pdf', \App\Http\Controllers\Admin\ProformaInvoicePdfController::class)
                    ->name('proforma-invoices.pdf');
                Route::get('/delivery-orders/{deliveryOrder}/pdf', DeliveryOrderPdfController::class)
                    ->name('delivery-orders.pdf');
                Route::get('/ocr-uploads/{ocrUpload}/document', OcrUploadDocumentController::class)
                    ->name('ocr-uploads.document');
                Route::get('/daily-lorry-schedule/pdf', \App\Http\Controllers\Admin\DailyLorrySchedulePdfController::class)
                    ->name('daily-lorry-schedule.pdf');
            })
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
                fn (): View => view('filament.hooks.datatable-theme'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.hooks.back-to-top'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.hooks.new-order-alert'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.brand-logo-theme'),
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
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.portal-enquiries-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.order-operations-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.order-workspace-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.notification-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.credit-customer-approval-theme'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.searchable-select'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.excel-filter'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.datatable-header'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.date-range-picker'),
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
