<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Receipt;
use App\Domains\Commission\Models\CommissionPurchaseOrder;
use App\Domains\Integration\Actions\SyncDocumentToAutoCount;
use App\Domains\Integration\Models\SyncLog;
use App\Domains\MasterData\Models\Branch;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class AutoCountSync extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'AutoCount Sync';

    protected static ?int $navigationSort = 60;

    protected static string $view = 'filament.pages.auto-count-sync';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'source_branch_id' => \App\Support\CurrentBranch::id() ?? auth()->user()?->defaultBranch()?->id,
            'document_type' => 'sales_invoice',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('source_branch_id')
                    ->default(fn () => \App\Support\CurrentBranch::id())
                    ->live()
                    ->required(),
                Forms\Components\Placeholder::make('branch_label')
                    ->label('Company / branch')
                    ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
                Forms\Components\Select::make('document_type')
                    ->options([
                        'sales_invoice' => 'Sales Invoice',
                        'ar_receipt' => 'AR Receipt',
                        'commission_po' => 'Commission PO/PI',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('document_id')
                    ->label('Document')
                    ->options(function (Forms\Get $get) {
                        $branchId = $get('source_branch_id');
                        $type = $get('document_type');
                        if (! $branchId || ! $type) {
                            return [];
                        }

                        return match ($type) {
                            'sales_invoice' => Invoice::query()
                                ->where('source_branch_id', $branchId)
                                ->orderByDesc('id')->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Invoice $i) => [
                                    $i->id => $i->number.' ['.$i->autocount_sync_status.'] RM '.number_format((float) $i->total_amount, 2),
                                ]),
                            'ar_receipt' => Receipt::query()
                                ->where('source_branch_id', $branchId)
                                ->orderByDesc('id')->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Receipt $r) => [
                                    $r->id => $r->number.' ['.$r->autocount_sync_status.']',
                                ]),
                            'commission_po' => CommissionPurchaseOrder::query()
                                ->where('source_branch_id', $branchId)
                                ->orderByDesc('id')->limit(50)
                                ->get()
                                ->mapWithKeys(fn (CommissionPurchaseOrder $po) => [
                                    $po->id => $po->po_number.' / '.$po->pi_number.' ['.$po->autocount_sync_status.']',
                                ]),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function syncSelected(bool $retry = false): void
    {
        $data = $this->form->getState();

        try {
            $document = match ($data['document_type']) {
                'sales_invoice' => Invoice::findOrFail($data['document_id']),
                'ar_receipt' => Receipt::findOrFail($data['document_id']),
                'commission_po' => CommissionPurchaseOrder::findOrFail($data['document_id']),
                default => throw new \InvalidArgumentException('Invalid type'),
            };

            $log = app(SyncDocumentToAutoCount::class)->execute(
                $data['document_type'],
                $document,
                auth()->user(),
                $retry
            );

            Notification::make()
                ->title($log->status === 'synced' ? 'Synced to AutoCount' : 'Sync failed')
                ->body(($log->external_ref ?? $log->error_message ?? '').' · retries '.$log->retry_count)
                ->color($log->status === 'synced' ? 'success' : 'danger')
                ->send();

            $this->resetTable();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SyncLog::query()
                ->with(['sourceBranch', 'syncedBy'])
                ->where('integration', 'autocount')
                ->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('When'),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('document_type')->badge(),
                Tables\Columns\TextColumn::make('document_id')->label('Doc ID'),
                Tables\Columns\TextColumn::make('external_ref')->label('AutoCount ref'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => $state === 'synced' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('retry_count'),
                Tables\Columns\TextColumn::make('error_message')->limit(40),
                Tables\Columns\TextColumn::make('syncedBy.name')->label('By'),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->visible(fn (SyncLog $record) => $record->status === 'failed')
                    ->action(function (SyncLog $record) {
                        $model = match ($record->document_type) {
                            'sales_invoice' => Invoice::find($record->document_id),
                            'ar_receipt' => Receipt::find($record->document_id),
                            'commission_po', 'commission_pi' => CommissionPurchaseOrder::find($record->document_id),
                            default => null,
                        };
                        if (! $model) {
                            Notification::make()->title('Document missing')->danger()->send();

                            return;
                        }
                        $type = $record->document_type === 'commission_pi' ? 'commission_po' : $record->document_type;
                        $log = app(SyncDocumentToAutoCount::class)->execute($type, $model, auth()->user(), true);
                        Notification::make()
                            ->title($log->status === 'synced' ? 'Retry OK' : 'Retry failed')
                            ->color($log->status === 'synced' ? 'success' : 'danger')
                            ->send();
                    }),
            ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq || $user?->hasAnyRole(['hq_admin', 'finance', 'branch_manager']);
    }
}
