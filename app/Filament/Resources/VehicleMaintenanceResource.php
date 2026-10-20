<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Actions\FlagVehicleMaintenanceDue;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use App\Filament\Resources\VehicleMaintenanceResource\Pages;
use App\Support\CurrentCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fleet → Vehicle Maintenance (user, 21 Oct 2026): one row per lorry with its insurance, road tax, permit and service
 * (each a maintenance record of its type). A row opens the lorry's records; Edit changes them together; New adds
 * records for a lorry.
 */
class VehicleMaintenanceResource extends Resource
{
    protected static ?string $model = Lorry::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'vehicle-maintenance-records';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'Vehicle Maintenance';

    protected static ?string $modelLabel = 'vehicle maintenance';

    protected static ?string $pluralModelLabel = 'Vehicle Maintenance Records';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'registration_no';

    public const TYPES = [
        'insurance' => 'Insurance',
        'road_tax' => 'Road tax',
        'permit' => 'Permit',
        'service' => 'Service',
        'oil' => 'Oil',
        'tyre' => 'Tyre',
        'repair' => 'Repair',
    ];

    /** Lorries are added in Master Data → Lorries / Drivers; here their maintenance records. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<int, Forms\Components\Component> one maintenance record's fields */
    public static function recordFields(): array
    {
        return [
            Forms\Components\Select::make('type')->options(self::TYPES)->required(),
            Forms\Components\DatePicker::make('service_date')->label('Service / start date'),
            Forms\Components\DatePicker::make('expiry_date')->label('Expiry date'),
            Forms\Components\TextInput::make('mileage')->numeric(),
            Forms\Components\TextInput::make('next_service_mileage')->label('Next service mileage')->numeric(),
            Forms\Components\DatePicker::make('next_service_date')->label('Next service date'),
            Forms\Components\TextInput::make('cost')->numeric()->prefix('RM'),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])
                ->default('active')
                ->required(),
            Forms\Components\FileUpload::make('attachment_path')->label('Attachment')->directory('vehicle-maintenance')->nullable(),
            Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('lorry')
                ->label('Lorry')
                ->content(fn (?Lorry $record) => $record ? $record->registration_no.($record->type ? ' · '.$record->type : '') : '—'),
            Forms\Components\Repeater::make('maintenanceRecords')
                ->label('Insurance, road tax, service, …')
                ->relationship()
                ->schema(static::recordFields())
                ->columns(3)
                ->itemLabel(fn (array $state) => (self::TYPES[$state['type'] ?? ''] ?? 'New record').(filled($state['expiry_date'] ?? null) ? ' · expires '.Carbon::parse($state['expiry_date'])->format('d/m/Y') : (filled($state['next_service_date'] ?? null) ? ' · next '.Carbon::parse($state['next_service_date'])->format('d/m/Y') : '')))
                ->collapsible()
                ->addActionLabel('Add maintenance type')
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('registration_no')->label('Lorry'),
                Infolists\Components\TextEntry::make('branch.name')->label('Branch')->placeholder('—'),
                Infolists\Components\TextEntry::make('type')->label('Lorry type')->placeholder('—'),
                Infolists\Components\TextEntry::make('capacity')->label('Capacity (kg)')->numeric()->placeholder('—'),
            ])->columns(4),
            Infolists\Components\ViewEntry::make('records')->hiddenLabel()->view('filament.infolists.vehicle-maintenance-records')->columnSpanFull(),
        ]);
    }

    /** The lorry's latest record of a type (not cancelled). */
    public static function latest(Lorry $lorry, string $type): ?VehicleMaintenanceRecord
    {
        return $lorry->maintenanceRecords
            ->where('type', $type)
            ->where('status', '!=', 'cancelled')
            ->sortByDesc(fn (VehicleMaintenanceRecord $record) => ($record->expiry_date ?? $record->next_service_date ?? $record->service_date)?->timestamp ?? 0)
            ->first();
    }

    protected static function isDue(mixed $date): bool
    {
        return $date instanceof Carbon && $date->lte(now()->addDays((int) config('og.vehicle.expiry_alert_days', 30)));
    }

    protected static function expiryColumn(string $type): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($type)
            ->label(self::TYPES[$type])
            ->state(fn (Lorry $record) => static::latest($record, $type)?->expiry_date)
            ->date('d M Y')
            ->color(fn ($state) => static::isDue($state) ? 'danger' : null)
            ->description(fn (Lorry $record) => ($cost = static::latest($record, $type)?->cost) !== null ? 'RM '.number_format((float) $cost, 2) : null)
            ->placeholder('—');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration_no')->label('Lorry')->searchable()->sortable()->weight('semibold')->description(fn (Lorry $record) => $record->type),
                Tables\Columns\TextColumn::make('branch.code')->label('Branch'),
                static::expiryColumn('insurance'),
                static::expiryColumn('road_tax'),
                static::expiryColumn('permit'),
                Tables\Columns\TextColumn::make('service')
                    ->label('Next service')
                    ->state(fn (Lorry $record) => static::latest($record, 'service')?->next_service_date)
                    ->date('d M Y')
                    ->color(fn ($state) => static::isDue($state) ? 'danger' : null)
                    ->description(fn (Lorry $record) => ($service = static::latest($record, 'service'))
                        ? (collect([$service->mileage ? 'at '.number_format((float) $service->mileage).' km' : null, $service->next_service_mileage ? 'next '.number_format((float) $service->next_service_mileage).' km' : null])->filter()->implode(' · ') ?: null)
                        : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('records_count')
                    ->label('Records')
                    ->state(fn (Lorry $record) => $record->maintenanceRecords->count())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total cost')
                    ->state(fn (Lorry $record) => (float) $record->maintenanceRecords->where('status', '!=', 'cancelled')->sum('cost'))
                    ->money('MYR'),
            ])
            ->defaultSort('registration_no')
            // a row shows the lorry's records (view); Edit changes them
            ->recordAction('view')
            ->recordUrl(null)
            ->filters([
                Tables\Filters\Filter::make('due_soon')
                    ->label('Due within alert window')
                    ->query(function (Builder $query) {
                        $until = now()->addDays((int) config('og.vehicle.expiry_alert_days', 30));

                        return $query->whereHas('maintenanceRecords', fn (Builder $q) => $q
                            ->where('status', 'active')
                            ->where(fn (Builder $inner) => $inner->whereDate('expiry_date', '<=', $until)->orWhereDate('next_service_date', '<=', $until)));
                    }),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Has a record of')
                    ->options(self::TYPES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('maintenanceRecords', fn (Builder $q) => $q->where('type', $data['value']))
                        : $query),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addRecords')
                    ->label('New vehicle maintenance record')
                    ->modalHeading('New vehicle maintenance record')
                    ->modalWidth('5xl')
                    ->form([
                        Forms\Components\Select::make('lorry_id')
                            ->label('Lorry')
                            ->options(fn () => static::getEloquentQuery()->orderBy('registration_no')->pluck('registration_no', 'id')->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\Repeater::make('records')
                            ->label('Insurance, road tax, service, …')
                            ->schema(static::recordFields())
                            ->columns(3)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add maintenance type'),
                    ])
                    ->modalSubmitActionLabel('Save')
                    ->action(function (array $data): void {
                        foreach ($data['records'] ?? [] as $record) {
                            VehicleMaintenanceRecord::query()->create(['lorry_id' => $data['lorry_id']] + $record);
                        }

                        Notification::make()->title(count($data['records'] ?? []).' maintenance record(s) added')->success()->send();
                    }),
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
                Tables\Actions\ViewAction::make()->modalHeading(fn (Lorry $record) => 'Maintenance · '.$record->registration_no)->modalWidth('5xl'),
                Tables\Actions\EditAction::make()->modalHeading(fn (Lorry $record) => 'Edit maintenance · '.$record->registration_no)->modalWidth('5xl'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_active', true)
            ->with(['branch:id,code,name', 'maintenanceRecords'])
            ->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where(fn (Builder $inner) => $inner->where('company_id', $id)->orWhereNull('company_id')));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVehicleMaintenance::route('/'),
        ];
    }
}
