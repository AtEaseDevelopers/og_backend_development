<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\TransferCode;
use App\Filament\Resources\TransferCodeResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransferCodeResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "masterdata.transfer_stores" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = TransferCode::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Transfer Codes';

    protected static ?int $navigationSort = 54;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Select::make('destination_branch_id')
                ->relationship('destinationBranch', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('type')
                ->options(\App\Domains\MasterData\Models\TransferCode::TYPES)
                ->required()
                ->default('transfer'),
            // the staff of this code: they assign its subsheets to a lorry (CSN page → Assign subsheets to lorry)
            Forms\Components\Select::make('staff')
                ->label('Staff')
                ->relationship('staff', 'name', fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', ['customer', 'driver']))
                    ->orderBy('name'))
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('They only see the CSNs with this transfer code on the CSN page (a super admin always sees all), and assign its subsheets to a lorry there (Assign subsheets to lorry).')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('destinationBranch.code')->label('Company'),
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => \App\Domains\MasterData\Models\TransferCode::TYPES[$state] ?? ucfirst((string) $state)),
                Tables\Columns\TextColumn::make('staff.name')->label('Staff')->listWithLineBreaks()->limitList(3)->expandableLimitedList()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Delete only for a record without history (client, 16 Nov 2026); a used one is set Inactive instead
                \App\Support\RecordUsage::deleteAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTransferCodes::route('/'),
        ];
    }
}
