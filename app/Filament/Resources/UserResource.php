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
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Staff accounts';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageStaff() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'customer'));
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
                ->label('Branch access')
                ->options(fn () => Branch::query()->where('is_active', true)->orderBy('code')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->dehydrated(false)
                ->visible(fn (string $operation) => $operation === 'create'),
            Forms\Components\Select::make('roles')
                ->relationship('roles', 'name')
                ->options(fn () => Role::query()
                    ->whereNotIn('name', ['customer', 'driver'])
                    ->orderBy('name')
                    ->pluck('name', 'name'))
                ->multiple()
                ->preload()
                ->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('branches.code')
                ->label('Branches')
                ->badge()
                ->separator(','),
            Tables\Columns\TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->separator(','),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
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
