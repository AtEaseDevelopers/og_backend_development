<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Reports (user, 21 Oct 2026): one card per report; a card opens the report on its own page (ReportDetail) with its
 * branch, date range, table and CSV export.
 */
class ReportsHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 80;

    protected static ?string $title = 'Reports';

    protected static string $view = 'filament.pages.reports-hub';

    /** @var array<string, array{label: string, description: string, icon: string, group: string, page?: string}> */
    public const REPORTS = [
        'csn_do' => ['label' => 'CSN / DO', 'description' => 'Delivery orders with their CSN, lorry, driver, status and delivery time.', 'icon' => 'heroicon-o-truck', 'group' => 'Operations'],
        'missing_csn' => ['label' => 'Missing CSN', 'description' => 'CSNs marked missing and their investigation status.', 'icon' => 'heroicon-o-exclamation-triangle', 'group' => 'Operations'],
        'break_bulk_psi' => ['label' => 'Break-bulk / PSI-PSO', 'description' => 'Break-bulk handovers between the original and replacement drivers.', 'icon' => 'heroicon-o-arrows-right-left', 'group' => 'Operations'],
        'vehicle' => ['label' => 'Vehicle maintenance / expiry', 'description' => 'Insurance, road tax, permit and service dates of each lorry.', 'icon' => 'heroicon-o-wrench-screwdriver', 'group' => 'Operations'],
        'payments_cod' => ['label' => 'Payments / COD', 'description' => 'Payments received (cash, COD, transfer, …) by customer and company.', 'icon' => 'heroicon-o-banknotes', 'group' => 'Finance'],
        'invoices' => ['label' => 'Invoice batches', 'description' => 'Invoices and Cash Bills issued, with their AutoCount status.', 'icon' => 'heroicon-o-document-text', 'group' => 'Finance'],
        'statements' => ['label' => 'Statements of Account', 'description' => 'Customers\' statements of account (invoices, payments, balance).', 'icon' => 'heroicon-o-clipboard-document', 'group' => 'Finance', 'page' => 'statements'],
        'cod_listing' => ['label' => 'COD Listing', 'description' => 'COD deliveries and the amounts drivers collect.', 'icon' => 'heroicon-o-truck', 'group' => 'Finance', 'page' => 'cod'],
        'commission' => ['label' => 'Commission (PO)', 'description' => 'Commission purchase orders per lorry and what each driver earned.', 'icon' => 'heroicon-o-calculator', 'group' => 'Finance'],
        'autocount' => ['label' => 'AutoCount sync', 'description' => 'Documents sent to AutoCount and their result.', 'icon' => 'heroicon-o-arrow-path', 'group' => 'Integrations'],
        'einvoice' => ['label' => 'e-Invoice', 'description' => 'e-Invoice submissions and their status.', 'icon' => 'heroicon-o-document-check', 'group' => 'Integrations'],
    ];

    /** A card's page: the report on ReportDetail, or its own page (Statements of Account, COD Listing). */
    public static function cardUrl(string $key): string
    {
        return match (self::REPORTS[$key]['page'] ?? null) {
            'statements' => \App\Filament\Resources\StatementResource::getUrl('index'),
            'cod' => CodListing::getUrl(),
            default => ReportDetail::getUrl(['report' => $key]),
        };
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'finance', 'dispatcher']);
    }
}
