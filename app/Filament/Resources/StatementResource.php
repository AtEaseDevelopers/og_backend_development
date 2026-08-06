<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Statement;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\StatementResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StatementResource extends Resource
{
    protected static ?string $model = Statement::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Statements of Account';

    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('source_branch_id')
                ->label('Branch')
                ->options(Branch::query()->pluck('name', 'id'))
                ->required(),
            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->options(Customer::query()->pluck('company_name', 'id'))
                ->required()
                ->searchable(),
            Forms\Components\DatePicker::make('statement_date')->default(now())->required(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('customer.company_name'),
                Infolists\Components\TextEntry::make('sourceBranch.name'),
                Infolists\Components\TextEntry::make('statement_date')->date(),
                Infolists\Components\TextEntry::make('outstanding_balance')->money('MYR'),
            ])->columns(2),
            Infolists\Components\Section::make('Aging')->schema([
                Infolists\Components\TextEntry::make('payload.aging.current')->label('Current')->money('MYR'),
                Infolists\Components\TextEntry::make('payload.aging.1_30')->label('1-30')->money('MYR'),
                Infolists\Components\TextEntry::make('payload.aging.31_60')->label('31-60')->money('MYR'),
                Infolists\Components\TextEntry::make('payload.aging.61_90')->label('61-90')->money('MYR'),
                Infolists\Components\TextEntry::make('payload.aging.90_plus')->label('90+')->money('MYR'),
            ])->columns(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->searchable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('statement_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('outstanding_balance')->money('MYR'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatements::route('/'),
            'create' => Pages\CreateStatement::route('/create'),
            'view' => Pages\ViewStatement::route('/{record}'),
        ];
    }
}
