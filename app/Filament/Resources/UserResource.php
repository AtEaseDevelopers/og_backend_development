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
    use \App\Filament\Concerns\HasModulePermissions;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Master Data';

    // opened from its card on the Master Data page (user, 21 Oct 2026), not the menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Users';

    protected static bool $isScopedToTenant = false;

    /** Open to the roles ticked for "settings.users" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'customer'))
            // a branch admin sees the staff of their companies only (user, 28 Oct 2026)
            ->when($user?->isBranchAdmin(), fn (Builder $q) => $q->whereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $user->accessibleBranchIds())));
    }

    /**
     * "Company access": the user works in exactly the companies picked. A branch admin changes only the companies
     * they manage themselves; the user's other companies stay.
     */
    public static function syncBranches(User $user, array $ids): void
    {
        $ids = array_map('intval', $ids);
        $mine = auth()->user()?->isBranchAdmin() ? auth()->user()->accessibleBranchIds() : null;
        $has = $user->branches()->pluck('branches.id')->map(fn ($id) => (int) $id);

        $removed = $has->reject(fn ($id) => in_array($id, $ids, true))
            ->when($mine !== null, fn ($c) => $c->intersect($mine))->values()->all();
        if ($removed) {
            $user->branches()->detach($removed);
            $user->companies()->detach(\App\Domains\MasterData\Models\Company::query()->whereIn('branch_id', $removed)->pluck('id')->all());
        }

        $hasDefault = $user->branches()->wherePivot('is_default', true)->exists();
        foreach (Branch::query()->whereIn('id', array_diff($ids, $has->all()))->orderBy('code')->get() as $branch) {
            $user->assignToBranch($branch, isDefault: ! $hasDefault);
            $hasDefault = true;
        }
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            // what the user types to sign in to the admin panel (user, 16 Nov 2026: the Staff ID, not the email)
            Forms\Components\TextInput::make('login_id')
                ->label('Staff ID')
                ->helperText('Used to sign in. Letters, numbers, dot, dash and underscore; not case-sensitive.')
                ->required()
                ->maxLength(60)
                ->regex('/^[A-Za-z0-9._-]+$/')
                ->validationMessages(['regex' => 'Use letters, numbers, dot, dash or underscore only (no spaces).'])
                ->dehydrateStateUsing(fn (?string $state) => User::normaliseLoginId((string) $state))
                ->unique(ignoreRecord: true)
                ->autocomplete('off'),
            Forms\Components\TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true),
            // Edit (user, 16 Nov 2026): the current password is shown as text with a "Change password" tick box; ticked,
            // the box to type a new one opens (shown as typed, not dots). The password is stored hashed; what is shown
            // is the encrypted copy kept when it was set on this page (password_readable) — none for an older one.
            Forms\Components\Group::make([
                // the current password in a read-only box, in the form's normal text (user, 16 Nov 2026)
                Forms\Components\TextInput::make('current_password')
                    ->label('Password')
                    ->readOnly()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?User $record) => $record?->password_readable)
                    ->placeholder('Not recorded · tick "Change password" to set a new one')
                    ->visible(fn (Forms\Get $get) => ! $get('change_password')),
                Forms\Components\TextInput::make('password')
                    ->label('New password')
                    ->autocomplete('off')
                    ->required()
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                    ->visible(fn (Forms\Get $get) => (bool) $get('change_password')),
                Forms\Components\Checkbox::make('change_password')
                    ->label('Change password')
                    ->live()
                    ->dehydrated(false),
            ])->visible(fn (string $operation) => $operation === 'edit'),
            Forms\Components\TextInput::make('password')
                ->autocomplete('off')
                ->visible(fn (string $operation) => $operation === 'create')
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
            // the companies the user works in, on New and on Edit (client, 17 Nov 2026: the one picked on a new user
            // was not saved, and there was no place to change it). The pages load and save it (syncBranches).
            Forms\Components\Select::make('branch_ids')
                ->label('Company access')
                ->options(fn () => Branch::query()->where('is_active', true)
                    ->when(auth()->user()?->isBranchAdmin(), fn ($q) => $q->whereIn('id', auth()->user()->accessibleBranchIds()))
                    ->orderBy('code')->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                // a super admin works in every company without one being picked
                ->required(fn (?User $record): bool => ! $record?->isSuperadmin())
                ->dehydrated(false),
            // one role a user (user, 16 Nov 2026: no more several roles). The field is not the relationship itself:
            // the pages load and save it (UserResource::staffRoleId / syncStaffRole), keeping a hidden role (driver,
            // customer) the account also has. Only a super admin gives the super admin role.
            Forms\Components\Select::make('role_id')
                ->label('Role')
                ->options(fn () => static::staffRoleOptions())
                ->native(false)
                ->dehydrated(false)
                // an account that is only a driver or a portal customer has no staff role to pick
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
            static::permissionsSection(),
        ]);
    }

    /**
     * "Permissions (this user only)" on the edit page (user, 13 Nov 2026): every action of Roles & Permission in a row,
     * as that page's grid, with a tick box — ticked = this user may do it. A box starts at what the user's roles give;
     * one that is changed is this user's own exception (allowed although the role is not, or removed although the role
     * is). Shown to those who may change roles and permissions; a super admin can always do everything, so there is
     * nothing to set. Loaded and saved by the EditUser page (the boxes are not columns of the user).
     */
    public static function permissionsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Permissions (this user only)')
            ->description(fn (?User $record) => $record?->isSuperadmin()
                ? 'A Super Admin can always do everything.'
                : 'Ticked = this user can do it. Each box starts at what the user\'s role gives; change a box to allow or remove the action for this user only. '
                    .(($count = $record ? count(\App\Support\Permissions::overridesFor($record)) : 0) > 0 ? $count.' changed for this user.' : 'None changed for this user.'))
            ->schema(fn (?User $record) => $record?->isSuperadmin() ? [] : [
                Forms\Components\ViewField::make('permission_effective')
                    ->hiddenLabel()
                    ->view('filament.forms.components.user-permissions')
                    ->viewData(['roleGrants' => $record ? \App\Support\Permissions::fromRoles($record) : []])
                    ->dehydrated(false),
            ])
            ->collapsible()
            ->collapsed(fn (?User $record) => ! $record || count(\App\Support\Permissions::overridesFor($record)) === 0)
            ->visible(fn (string $operation, ?User $record) => $operation === 'edit' && $record && \App\Support\Permissions::allows('settings.roles')
                && ! $record->hasAnyRole(['customer', 'driver']));
    }

    /** The roles of the Role field: id => name as shown; never the hidden ones, the super admin only for a super admin. @return array<int, string> */
    public static function staffRoleOptions(): array
    {
        return \Spatie\Permission\Models\Role::query()
            ->whereNotIn('name', ['customer', 'driver'])
            ->when(! auth()->user()?->isSuperadmin(), fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn (string $name) => \Illuminate\Support\Str::headline($name))
            ->all();
    }

    /** The user's staff role (the first, for an older account that has several). */
    public static function staffRoleId(User $user): ?int
    {
        $id = $user->roles()->whereNotIn('name', ['customer', 'driver'])->orderBy('roles.id')->value('roles.id');

        return $id ? (int) $id : null;
    }

    /** Gives the user the one staff role picked; their hidden roles (driver, customer) stay; no pick leaves the roles alone. */
    public static function syncStaffRole(User $user, mixed $roleId): void
    {
        if (blank($roleId) || ! array_key_exists((int) $roleId, static::staffRoleOptions())) {
            return;
        }

        $hidden = $user->roles()->whereIn('name', ['customer', 'driver'])->pluck('roles.id')->all();
        $user->roles()->sync(array_values(array_unique([...$hidden, (int) $roleId])));
        $user->unsetRelation('roles');
        \App\Support\Permissions::flush();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('login_id')->label('Staff ID')->searchable()->sortable()->copyable()->weight('semibold')->placeholder('—'),
            // the password an admin set on this page, beside the Staff ID (user, 16 Nov 2026); "—" for one with no copy
            // (set before 16 Nov, or changed elsewhere). Only for those who may edit users, as on the edit form.
            Tables\Columns\TextColumn::make('password_readable')
                ->label('Password')
                ->getStateUsing(fn (User $record) => $record->password_readable)
                ->copyable()
                ->placeholder('—')
                ->visible(fn () => \App\Support\Permissions::allows('users.edit')),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('branches.code')
                ->label('Companies')
                ->badge()
                ->separator(','),
            Tables\Columns\TextColumn::make('roles.name')
                ->label('Role')
                ->formatStateUsing(fn (?string $state) => \Illuminate\Support\Str::headline((string) $state))
                ->badge()
                ->separator(','),
            Tables\Columns\TextColumn::make('saLocation.code')
                ->label('Branch')
                ->placeholder('—'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])
            // the Role filter is a dropdown beside the search box (ListUsers), not a filter card
            ->actions([
            // View beside Edit on every Master Data list (user, 16 Nov 2026): the record in a window, read-only
            Tables\Actions\ViewAction::make(),
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
