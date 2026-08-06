<?php

namespace App\Filament\Pages;

use App\Domains\Billing\Actions\ReconcileCodCollections;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class CodReconciliation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'COD Reconciliation';

    protected static ?int $navigationSort = 23;

    protected static string $view = 'filament.pages.cod-reconciliation';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'source_branch_id' => \App\Support\CurrentBranch::id() ?? auth()->user()?->defaultBranch()?->id,
            'returned_amount' => 0,
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
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->options(fn () => Driver::query()
                        ->where('is_active', true)
                        ->when(\App\Support\CurrentBranch::id(), fn ($q, $id) => $q->where('branch_id', $id))
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('returned_amount')
                    ->label('Amount returned by driver')
                    ->numeric()
                    ->required(),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->statePath('data');
    }

    public function reconcile(): void
    {
        $data = $this->form->getState();

        try {
            $result = app(ReconcileCodCollections::class)->execute(
                Driver::findOrFail($data['driver_id']),
                (int) $data['source_branch_id'],
                (float) $data['returned_amount'],
                auth()->user(),
                $data['remarks'] ?? null
            );

            Notification::make()
                ->title('COD reconciled')
                ->body(sprintf(
                    'Expected RM %s · Returned RM %s · Shortage RM %s (%d collections)',
                    number_format($result['expected'], 2),
                    number_format($result['returned'], 2),
                    number_format($result['shortage'], 2),
                    $result['payments']
                ))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
