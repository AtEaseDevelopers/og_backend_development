<?php

namespace App\Filament\Pages;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\FlagMissingCsns;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\MasterData\Models\Driver;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class ReturnedCsnDesk extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Delivery';

    protected static ?string $navigationLabel = 'Returned CSNs';

    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.returned-csn-desk';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mode' => 'select',
            'is_signed' => true,
            'is_stamped' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('mode')
                    ->options([
                        'select' => 'Select pending CSN',
                        'qr' => 'Scan / enter QR token',
                    ])
                    ->live()
                    ->required(),
                Forms\Components\Select::make('consignment_note_id')
                    ->label('Pending return CSN')
                    ->options(fn () => ConsignmentNote::query()
                        ->whereIn('return_status', ['pending_return', 'missing'])
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (ConsignmentNote $csn) => [
                            $csn->id => $csn->number.' ['.$csn->return_status.'] '.$csn->customer_name,
                        ]))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('mode') === 'select')
                    ->required(fn (Forms\Get $get) => $get('mode') === 'select'),
                Forms\Components\TextInput::make('qr_token')
                    ->label('CSN QR token')
                    ->visible(fn (Forms\Get $get) => $get('mode') === 'qr')
                    ->required(fn (Forms\Get $get) => $get('mode') === 'qr'),
                Forms\Components\Select::make('returned_by_driver_id')
                    ->label('Returned by driver')
                    ->options(fn () => Driver::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Toggle::make('is_signed')->label('Signed')->default(true),
                Forms\Components\Toggle::make('is_stamped')->label('Stamped'),
                Forms\Components\Textarea::make('remarks'),
            ])
            ->statePath('data');
    }

    public function receive(): void
    {
        $data = $this->form->getState();

        try {
            if (($data['mode'] ?? 'select') === 'qr') {
                $returned = app(RecordReturnedCsn::class)->executeByQrToken(
                    $data['qr_token'],
                    $data,
                    auth()->user()
                );
            } else {
                $csn = ConsignmentNote::query()->findOrFail($data['consignment_note_id']);
                $returned = app(RecordReturnedCsn::class)->execute($csn, $data, auth()->user());
            }

            Notification::make()
                ->title('CSN returned')
                ->body($returned->consignmentNote?->number.' — commission gate released for draft slips')
                ->success()
                ->send();

            $this->form->fill([
                'mode' => $data['mode'],
                'is_signed' => true,
                'is_stamped' => false,
            ]);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function flagMissing(): void
    {
        $logs = app(FlagMissingCsns::class)->execute();
        Notification::make()
            ->title('Missing CSN sweep complete')
            ->body($logs->count().' CSN(s) marked missing')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'counter', 'storekeeper', 'finance']);
    }
}
