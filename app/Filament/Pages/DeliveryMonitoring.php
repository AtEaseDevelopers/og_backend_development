<?php

namespace App\Filament\Pages;

use App\Domains\Delivery\Actions\FlagIncompleteDeliveries;
use App\Domains\Delivery\Models\IncompleteDeliveryAlert;
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

class DeliveryMonitoring extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Delivery Monitoring';

    protected static ?int $navigationSort = 56;

    protected static string $view = 'filament.pages.delivery-monitoring';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'alert_date' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('alert_date')
                    ->label('Operating / alert date')
                    ->required()
                    ->live(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = IncompleteDeliveryAlert::query()
                    ->with(['deliveryOrder.consignmentNote', 'jobSheet', 'branch', 'acknowledgedBy']);

                if (! empty($this->data['alert_date'])) {
                    $query->whereDate('alert_date', $this->data['alert_date']);
                }

                return $query->latest('id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('alert_date')->date(),
                Tables\Columns\TextColumn::make('deliveryOrder.number')->label('DO')->searchable(),
                Tables\Columns\TextColumn::make('deliveryOrder.status')->label('DO status')->badge(),
                Tables\Columns\TextColumn::make('jobSheet.number')->label('Job Sheet'),
                Tables\Columns\TextColumn::make('branch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('deliveryOrder.driver.name')->label('Driver'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'acknowledged' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notified_at')->dateTime(),
                Tables\Columns\TextColumn::make('acknowledgedBy.name')->label('Ack by'),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->visible(fn (IncompleteDeliveryAlert $record) => $record->status === 'open')
                    ->action(function (IncompleteDeliveryAlert $record) {
                        $record->update([
                            'status' => 'acknowledged',
                            'acknowledged_by' => auth()->id(),
                            'acknowledged_at' => now(),
                        ]);
                        Notification::make()->title('Acknowledged')->success()->send();
                    }),
                Tables\Actions\Action::make('resolve')
                    ->visible(fn (IncompleteDeliveryAlert $record) => in_array($record->status, ['open', 'acknowledged'], true))
                    ->action(function (IncompleteDeliveryAlert $record) {
                        $record->update([
                            'status' => 'resolved',
                            'acknowledged_by' => $record->acknowledged_by ?? auth()->id(),
                            'acknowledged_at' => $record->acknowledged_at ?? now(),
                        ]);
                        Notification::make()->title('Resolved')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No incomplete-delivery alerts for this date');
    }

    public function runFlag(): void
    {
        $date = \Illuminate\Support\Carbon::parse($this->data['alert_date'] ?? now());
        $alerts = app(FlagIncompleteDeliveries::class)->execute($date, notify: true);

        Notification::make()
            ->title('Incomplete deliveries flagged')
            ->body($alerts->count().' task(s) for '.$date->toDateString())
            ->success()
            ->send();

        $this->resetTable();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_hq
            || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'dispatcher']);
    }
}
