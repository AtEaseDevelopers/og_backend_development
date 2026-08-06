<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Actions\FlagVehicleMaintenanceDue;
use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use App\Filament\Resources\VehicleMaintenanceResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleMaintenanceResource extends Resource
{
    protected static ?string $model = VehicleMaintenanceRecord::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'Vehicle Maintenance';

    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('lorry_id')
                ->relationship('lorry', 'registration_no')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('type')
                ->options([
                    'service' => 'Service',
                    'permit' => 'Permit',
                    'insurance' => 'Insurance',
                    'road_tax' => 'Road tax',
                    'oil' => 'Oil',
                    'tyre' => 'Tyre',
                    'repair' => 'Repair',
                ])
                ->required(),
            Forms\Components\DatePicker::make('service_date'),
            Forms\Components\DatePicker::make('expiry_date'),
            Forms\Components\TextInput::make('mileage')->numeric(),
            Forms\Components\TextInput::make('next_service_mileage')->numeric(),
            Forms\Components\DatePicker::make('next_service_date'),
            Forms\Components\TextInput::make('cost')->numeric()->prefix('RM'),
            Forms\Components\FileUpload::make('attachment_path')->directory('vehicle-maintenance')->nullable(),
            Forms\Components\Select::make('status')
                ->options([
                    'active' => 'Active',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])
                ->default('active')
                ->required(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lorry.registration_no')->label('Lorry')->searchable(),
                Tables\Columns\TextColumn::make('lorry.branch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('service_date')->date(),
                Tables\Columns\TextColumn::make('expiry_date')->date()
                    ->color(fn ($state) => $state && $state->lte(now()->addDays(30)) ? 'danger' : null),
                Tables\Columns\TextColumn::make('next_service_date')->date(),
                Tables\Columns\TextColumn::make('mileage'),
                Tables\Columns\TextColumn::make('cost')->money('MYR'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('alerted_at')->dateTime(),
            ])
            ->defaultSort('expiry_date')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'service' => 'Service',
                    'permit' => 'Permit',
                    'insurance' => 'Insurance',
                    'road_tax' => 'Road tax',
                    'oil' => 'Oil',
                    'tyre' => 'Tyre',
                    'repair' => 'Repair',
                ]),
                Tables\Filters\Filter::make('due_soon')
                    ->label('Due within alert window')
                    ->query(function ($query) {
                        $until = now()->addDays((int) config('og.vehicle.expiry_alert_days', 30));

                        return $query->where(function ($q) use ($until) {
                            $q->whereDate('expiry_date', '<=', $until)
                                ->orWhereDate('next_service_date', '<=', $until);
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('run_alerts')
                    ->label('Run due alerts')
                    ->action(function () {
                        $due = app(FlagVehicleMaintenanceDue::class)->execute();
                        Notification::make()
                            ->title('Vehicle alerts')
                            ->body($due->count().' record(s) due soon')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = \App\Support\CurrentCompany::id();
        if ($companyId) {
            $query->whereHas('lorry', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVehicleMaintenance::route('/'),
        ];
    }
}
