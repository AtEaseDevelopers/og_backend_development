<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\CommissionRule;
use App\Filament\Resources\CommissionRuleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionRuleResource extends Resource
{
    protected static ?string $model = CommissionRule::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Commission';

    protected static ?string $navigationLabel = 'Commission Rules';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('source_branch_id')
                ->relationship('sourceBranch', 'name')
                ->nullable()
                ->helperText('Leave empty for all branches'),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('lorry_type')->nullable(),
            Forms\Components\TextInput::make('route')->nullable(),
            Forms\Components\Select::make('split_type')
                ->options([
                    'single' => 'Single driver',
                    'split_2' => 'Split 2',
                    'split_3' => 'Split 3',
                    'split_4' => 'Split 4',
                ])
                ->required()
                ->live()
                ->default('single'),
            Forms\Components\TextInput::make('rate_percent')
                ->numeric()
                ->required()
                ->default(10)
                ->suffix('% of freight'),
            Forms\Components\TagsInput::make('percentages.shares')
                ->label('Share % per driver')
                ->helperText('e.g. 50, 50 for split_2. Defaults apply if empty.')
                ->suggestions(['100', '50', '40', '30', '25']),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch')->placeholder('All'),
                Tables\Columns\TextColumn::make('lorry_type')->placeholder('Any'),
                Tables\Columns\TextColumn::make('split_type')->badge(),
                Tables\Columns\TextColumn::make('rate_percent')->suffix('%'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCommissionRules::route('/'),
        ];
    }
}
