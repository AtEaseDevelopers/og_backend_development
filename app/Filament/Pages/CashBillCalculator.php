<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Enums\CsnBillingType;
use App\Enums\PaymentStatus;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CashBillCalculator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Cash Bill Calculator';

    protected static ?int $navigationSort = 19;

    protected static string $view = 'filament.pages.cash-bill-calculator';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'source_branch_id' => \App\Support\CurrentBranch::id() ?? auth()->user()?->defaultBranch()?->id,
            'method' => 'cash',
            'amount_received' => 0,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('source_branch_id')
                    ->default(fn () => \App\Support\CurrentBranch::id())
                    ->required(),
                Forms\Components\Placeholder::make('branch_label')
                    ->label('Company / branch')
                    ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
                Forms\Components\CheckboxList::make('consignment_note_ids')
                    ->label('Unpaid Cash Bill CSNs')
                    ->options(function (Forms\Get $get) {
                        $branchId = $get('source_branch_id');
                        if (! $branchId) {
                            return [];
                        }

                        return ConsignmentNote::query()
                            ->where('source_branch_id', $branchId)
                            ->where('billing_type', CsnBillingType::CashBill)
                            ->where('payment_status', '!=', PaymentStatus::Paid->value)
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (ConsignmentNote $csn) => [
                                $csn->id => $csn->number.' — '.$csn->customer_name.' — RM '.number_format((float) $csn->total_amount, 2),
                            ])
                            ->all();
                    })
                    ->required()
                    ->columns(1)
                    ->live(),
                Forms\Components\Select::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'ewallet' => 'eWallet',
                        'bank_transfer' => 'Bank Transfer',
                        'online' => 'Online Payment',
                        'counter' => 'Pay at Counter',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount_received')
                    ->numeric()
                    ->required()
                    ->helperText(fn (Forms\Get $get) => 'Selected total: RM '.number_format($this->selectedTotal($get), 2)),
                Forms\Components\TextInput::make('reference'),
            ])
            ->statePath('data');
    }

    public function process(): void
    {
        $data = $this->form->getState();
        $ids = $data['consignment_note_ids'] ?? [];
        $csns = ConsignmentNote::query()->whereIn('id', $ids)->get();
        $total = (float) $csns->sum('total_amount');
        $received = (float) $data['amount_received'];

        if ($received + 0.0001 < $total) {
            Notification::make()
                ->title('Insufficient amount')
                ->body('Selected total is RM '.number_format($total, 2))
                ->danger()
                ->send();

            return;
        }

        foreach ($csns as $csn) {
            app(RecordPayment::class)->execute([
                'source_branch_id' => $csn->source_branch_id,
                'consignment_note_id' => $csn->id,
                'customer_id' => $csn->customer_id,
                'amount' => $csn->total_amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ], auth()->user());
        }

        $change = round($received - $total, 2);
        Notification::make()
            ->title('Collected '.count($ids).' Cash Bill(s)')
            ->body('Change: RM '.number_format($change, 2))
            ->success()
            ->send();

        $this->form->fill([
            'source_branch_id' => $data['source_branch_id'],
            'method' => $data['method'],
            'amount_received' => 0,
            'consignment_note_ids' => [],
        ]);
    }

    private function selectedTotal(Forms\Get $get): float
    {
        $ids = $get('consignment_note_ids') ?? [];
        if ($ids === []) {
            return 0;
        }

        return (float) ConsignmentNote::query()->whereIn('id', $ids)->sum('total_amount');
    }
}
