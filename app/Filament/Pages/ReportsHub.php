<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Commission\Models\CommissionSlip;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Domains\Integration\Models\SyncLog;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsHub extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.pages.reports-hub';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'report' => 'csn_do',
            'source_branch_id' => \App\Support\CurrentBranch::id() ?? auth()->user()?->defaultBranch()?->id,
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('report')
                    ->options([
                        'csn_do' => 'CSN / DO',
                        'missing_csn' => 'Missing CSN',
                        'payments_cod' => 'Payments / COD',
                        'commission' => 'Commission slips',
                        'break_bulk_psi' => 'Break-bulk / PSI-PSO',
                        'invoices' => 'Invoice batches',
                        'autocount' => 'AutoCount sync',
                        'einvoice' => 'e-Invoice',
                        'vehicle' => 'Vehicle maintenance / expiry',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Hidden::make('source_branch_id')
                    ->default(fn () => \App\Support\CurrentBranch::id()),
                Forms\Components\Placeholder::make('branch_label')
                    ->label('Company / branch')
                    ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
                Forms\Components\DatePicker::make('from')->live(),
                Forms\Components\DatePicker::make('to')->live(),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->reportQuery())
            ->columns($this->reportColumns())
            ->paginated([25, 50, 100])
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Export CSV')
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    protected function reportQuery(): Builder
    {
        $report = $this->data['report'] ?? 'csn_do';
        $branchId = $this->data['source_branch_id'] ?? null;
        $from = $this->data['from'] ?? null;
        $to = $this->data['to'] ?? null;

        return match ($report) {
            'missing_csn' => MissingCsnLog::query()
                ->with(['consignmentNote', 'sourceBranch'])
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'payments_cod' => Payment::query()
                ->with(['customer', 'sourceBranch'])
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'commission' => CommissionSlip::query()
                ->with(['driver', 'sourceBranch', 'batch'])
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'break_bulk_psi' => BreakBulk::query()
                ->with(['deliveryOrder', 'originalDriver', 'replacementDriver'])
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'invoices' => Invoice::query()
                ->with(['customer', 'sourceBranch'])
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))
                ->latest('id'),
            'autocount' => SyncLog::query()
                ->with('sourceBranch')
                ->where('integration', 'autocount')
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'einvoice' => EinvoiceSubmission::query()
                ->with(['invoice.sourceBranch'])
                ->when($branchId, fn ($q) => $q->whereHas('invoice', fn ($i) => $i->where('source_branch_id', $branchId)))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
            'vehicle' => VehicleMaintenanceRecord::query()
                ->with('lorry.branch')
                ->when($branchId, fn ($q) => $q->whereHas('lorry', fn ($l) => $l->where('branch_id', $branchId)))
                ->latest('id'),
            default => DeliveryOrder::query()
                ->with(['consignmentNote', 'sourceBranch', 'lorry', 'driver'])
                ->when($branchId, fn ($q) => $q->where('source_branch_id', $branchId))
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id'),
        };
    }

    /** @return array<int, Tables\Columns\Column> */
    protected function reportColumns(): array
    {
        return match ($this->data['report'] ?? 'csn_do') {
            'missing_csn' => [
                Tables\Columns\TextColumn::make('consignmentNote.number')->label('CSN'),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('marked_missing_at')->dateTime(),
                Tables\Columns\TextColumn::make('investigation_status'),
            ],
            'payments_cod' => [
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('customer.company_name'),
                Tables\Columns\TextColumn::make('method')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('MYR'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ],
            'commission' => [
                Tables\Columns\TextColumn::make('number'),
                Tables\Columns\TextColumn::make('batch.month')->label('Month'),
                Tables\Columns\TextColumn::make('sourceBranch.code'),
                Tables\Columns\TextColumn::make('driver.name'),
                Tables\Columns\TextColumn::make('system_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('final_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ],
            'break_bulk_psi' => [
                Tables\Columns\TextColumn::make('number'),
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO'),
                Tables\Columns\TextColumn::make('originalDriver.name')->label('Original'),
                Tables\Columns\TextColumn::make('replacementDriver.name')->label('Replacement'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('handover_status'),
            ],
            'invoices' => [
                Tables\Columns\TextColumn::make('number'),
                Tables\Columns\TextColumn::make('sourceBranch.code'),
                Tables\Columns\TextColumn::make('customer.company_name'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('billing_month'),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('autocount_sync_status')->label('AutoCount'),
            ],
            'autocount' => [
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
                Tables\Columns\TextColumn::make('sourceBranch.code'),
                Tables\Columns\TextColumn::make('document_type'),
                Tables\Columns\TextColumn::make('document_id'),
                Tables\Columns\TextColumn::make('external_ref'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('retry_count'),
            ],
            'einvoice' => [
                Tables\Columns\TextColumn::make('invoice.number'),
                Tables\Columns\TextColumn::make('invoice.sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('uuid')->limit(20),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
            ],
            'vehicle' => [
                Tables\Columns\TextColumn::make('lorry.registration_no'),
                Tables\Columns\TextColumn::make('lorry.branch.code'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('expiry_date')->date(),
                Tables\Columns\TextColumn::make('next_service_date')->date(),
                Tables\Columns\TextColumn::make('status'),
            ],
            default => [
                Tables\Columns\TextColumn::make('number')->label('DO'),
                Tables\Columns\TextColumn::make('consignmentNote.number')->label('CSN'),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('lorry.registration_no')->label('Lorry'),
                Tables\Columns\TextColumn::make('driver.name'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('delivered_at')->dateTime(),
            ],
        };
    }

    public function exportCsv(): StreamedResponse
    {
        $report = $this->data['report'] ?? 'csn_do';
        $rows = $this->reportQuery()->limit(2000)->get();
        $filename = 'report-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $report) {
            $out = fopen('php://output', 'w');
            if ($rows->isEmpty()) {
                fputcsv($out, ['no_data']);
                fclose($out);

                return;
            }

            $first = $this->flattenRow($rows->first(), $report);
            fputcsv($out, array_keys($first));
            foreach ($rows as $row) {
                fputcsv($out, array_values($this->flattenRow($row, $report)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    protected function flattenRow(object $row, string $report): array
    {
        return match ($report) {
            'missing_csn' => [
                'csn' => $row->consignmentNote?->number,
                'branch' => $row->sourceBranch?->code,
                'status' => $row->status,
                'marked_missing_at' => optional($row->marked_missing_at)?->toDateTimeString(),
            ],
            'payments_cod' => [
                'id' => $row->id,
                'branch' => $row->sourceBranch?->code,
                'customer' => $row->customer?->company_name,
                'method' => $row->method,
                'amount' => $row->amount,
                'status' => $row->status,
            ],
            'commission' => [
                'number' => $row->number,
                'month' => $row->batch?->month,
                'branch' => $row->sourceBranch?->code,
                'driver' => $row->driver?->name,
                'system' => $row->system_amount,
                'final' => $row->final_amount,
                'status' => $row->status,
            ],
            'invoices' => [
                'number' => $row->number,
                'branch' => $row->sourceBranch?->code,
                'customer' => $row->customer?->company_name,
                'type' => $row->type,
                'total' => $row->total_amount,
                'autocount' => $row->autocount_sync_status,
            ],
            'autocount' => [
                'when' => optional($row->created_at)?->toDateTimeString(),
                'branch' => $row->sourceBranch?->code,
                'type' => $row->document_type,
                'doc_id' => $row->document_id,
                'ref' => $row->external_ref,
                'status' => $row->status,
            ],
            'einvoice' => [
                'invoice' => $row->invoice?->number,
                'status' => $row->status,
                'uuid' => $row->uuid,
            ],
            'vehicle' => [
                'lorry' => $row->lorry?->registration_no,
                'type' => $row->type,
                'expiry' => optional($row->expiry_date)?->toDateString(),
                'next_service' => optional($row->next_service_date)?->toDateString(),
            ],
            'break_bulk_psi' => [
                'number' => $row->number,
                'do' => $row->deliveryOrder?->number,
                'status' => $row->status,
            ],
            default => [
                'do' => $row->number,
                'csn' => $row->consignmentNote?->number,
                'branch' => $row->sourceBranch?->code,
                'lorry' => $row->lorry?->registration_no,
                'driver' => $row->driver?->name,
                'status' => $row->status?->value ?? $row->status,
            ],
        };
    }

    public function updatedData(): void
    {
        $this->resetTable();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'finance', 'dispatcher']);
    }
}
