<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Staff accounts';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageStaff() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'customer'))
            // a branch admin sees the staff of their companies only (user, 28 Oct 2026)
            ->when($user?->isBranchAdmin(), fn (Builder $q) => $q->whereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $user->accessibleBranchIds())));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->password()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            Forms\Components\Select::make('branch_id')
                ->label('Company access')
                ->options(fn () => Branch::query()->where('is_active', true)
                    ->when(auth()->user()?->isBranchAdmin(), fn ($q) => $q->whereIn('id', auth()->user()->accessibleBranchIds()))
                    ->orderBy('code')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->dehydrated(false)
                ->visible(fn (string $operation) => $operation === 'create'),
            Forms\Components\Select::make('roles')
                // Filter inside the relationship so options, search results, the loaded state and
                // the pivot sync are all keyed by role id. A separate name-keyed ->options() list
                // disagreed with the id-keyed search results and synced names as role_id 0.
                ->relationship('roles', 'name', fn (Builder $query) => $query
                    ->whereNotIn($query->qualifyColumn('name'), ['customer', 'driver'])
                    // only a super admin gives the super_admin role
                    ->when(! auth()->user()?->isSuperadmin(), fn (Builder $q) => $q->where($q->qualifyColumn('name'), '!=', 'super_admin')))
                ->multiple()
                ->preload()
                // The filter also applies to the loaded state, so a driver-only account loads with no
                // roles. Its hidden role is never detached on save, so it keeps one: only accounts
                // without a hidden role have to pick one here.
                ->required(fn (?User $record): bool => ! $record?->hasAnyRole(['customer', 'driver'])),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Section::make('Sales ownership (section A)')
                ->description('Each salesperson belongs to one branch; the branch carries the CSN prefix. The ordering link fixes the salesperson on every order a customer submits through it.')
                ->schema([
                    Forms\Components\Select::make('sa_location_id')
                        ->label('Branch')
                        ->options(fn () => \App\Domains\MasterData\Models\SaLocation::query()->where('is_active', true)->orderBy('code')->get()
                            ->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name.' ('.$l->csn_prefix.')']))
                        ->searchable(),
                    Forms\Components\Placeholder::make('ordering_link')
                        ->label('Customer ordering link')
                        ->content(fn (?User $record) => $record?->ordering_token
                            ? new \Illuminate\Support\HtmlString('<code class="text-xs">'.e($record->orderingLink()).'</code>')
                            : 'Save the account, then use "Generate ordering link" on the edit page.')
                        ->visible(fn (string $operation) => $operation === 'edit'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('branches.code')
                ->label('Companies')
                ->badge()
                ->separator(','),
            Tables\Columns\TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->separator(','),
            Tables\Columns\TextColumn::make('saLocation.code')
                ->label('Branch')
                ->placeholder('—'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('orderingLink')
                ->label(fn (User $record) => $record->ordering_token ? 'Copy ordering link' : 'Generate ordering link')
                ->icon('heroicon-o-link')
                ->visible(fn (User $record) => $record->hasRole('salesperson'))
                ->action(function (User $record) {
                    $record->ensureOrderingToken();

                    \Filament\Notifications\Notification::make()
                        ->title('Ordering link for '.$record->name)
                        ->body($record->orderingLink())
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
