<?php

namespace App\Filament\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\CsnStatus;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class SharedDispatch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Shared Dispatch';

    protected static ?int $navigationSort = 51;

    protected static string $view = 'filament.pages.shared-dispatch';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'operating_date' => now()->toDateString(),
            'assignment_mode' => 'select',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('operating_date')->required()->live(),
                Forms\Components\Select::make('lorry_id')
                    ->label('Lorry (all branches)')
                    ->options(function () {
                        return Lorry::query()
                            ->with('branch')
                            ->where('is_active', true)
                            ->orderBy('registration_no')
                            ->get()
                            ->mapWithKeys(fn (Lorry $lorry) => [
                                $lorry->id => sprintf(
                                    '%s [%s] %s',
                                    $lorry->registration_no,
                                    $lorry->branch?->code,
                                    $lorry->status
                                ),
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\Select::make('assignment_mode')
                    ->options([
                        'select' => 'Select CSN',
                        'qr' => 'Scan / enter CSN QR token',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('consignment_note_id')
                    ->label('Unassigned CSN (this company)')
                    ->options(function () {
                        return ConsignmentNote::query()
                            ->with('sourceBranch')
                            ->when(
                                \App\Support\CurrentBranch::id(),
                                fn ($q, $id) => $q->where('source_branch_id', $id)
                            )
                            ->whereDoesntHave('deliveryOrder')
                            ->where('status', '!=', CsnStatus::Cancelled)
                            ->orderByDesc('id')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (ConsignmentNote $csn) => [
                                $csn->id => sprintf(
                                    '%s [%s] %s — %s',
                                    $csn->number,
                                    $csn->sourceBranch?->code,
                                    $csn->customer_name,
                                    $csn->billing_type?->value
                                ),
                            ]);
                    })
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('assignment_mode') === 'select')
                    ->required(fn (Forms\Get $get) => $get('assignment_mode') === 'select'),
                Forms\Components\TextInput::make('qr_token')
                    ->label('CSN QR token')
                    ->visible(fn (Forms\Get $get) => $get('assignment_mode') === 'qr')
                    ->required(fn (Forms\Get $get) => $get('assignment_mode') === 'qr'),
                Forms\Components\Placeholder::make('availability')
                    ->label('Daily Job Sheets for selected date')
                    ->content(function (Forms\Get $get) {
                        $date = $get('operating_date');
                        if (! $date) {
                            return 'Select a date';
                        }

                        $sheets = JobSheet::query()
                            ->with(['lorry.branch', 'driver'])
                            ->whereDate('operating_date', $date)
                            ->orderBy('number')
                            ->get();

                        if ($sheets->isEmpty()) {
                            return 'No job sheets yet for this date.';
                        }

                        return $sheets->map(fn (JobSheet $js) => sprintf(
                            '%s — %s [%s] / %s%s',
                            $js->number,
                            $js->lorry?->registration_no,
                            $js->lorry?->branch?->code,
                            $js->driver?->name ?? 'no driver',
                            $js->is_shared_dispatch ? ' (shared)' : ''
                        ))->implode("\n");
                    }),
            ])
            ->statePath('data');
    }

    public function assign(): void
    {
        $data = $this->form->getState();

        try {
            if (($data['assignment_mode'] ?? 'select') === 'qr') {
                $csn = ConsignmentNote::query()->where('qr_token', $data['qr_token'])->firstOrFail();
            } else {
                $csn = ConsignmentNote::query()->findOrFail($data['consignment_note_id']);
            }

            $do = app(AssignCsnToLorry::class)->execute(
                $csn,
                Lorry::findOrFail($data['lorry_id']),
                $data['operating_date']
            );

            Notification::make()
                ->title('Assigned via shared dispatch')
                ->body(sprintf(
                    'CSN %s → DO %s on Job Sheet %s (lorry branch %s, source %s)',
                    $csn->number,
                    $do->number,
                    $do->jobSheet?->number,
                    $do->lorry?->branch?->code,
                    $do->sourceBranch?->code
                ))
                ->success()
                ->send();

            $this->form->fill([
                'operating_date' => $data['operating_date'],
                'lorry_id' => $data['lorry_id'],
                'assignment_mode' => $data['assignment_mode'],
            ]);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'dispatcher']);
    }
}
