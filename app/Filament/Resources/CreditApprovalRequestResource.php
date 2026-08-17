<?php

namespace App\Filament\Resources;

use App\Domains\Quotation\Actions\DecideCreditApproval;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Filament\Resources\CreditApprovalRequestResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreditApprovalRequestResource extends Resource
{
    protected static ?string $model = CreditApprovalRequest::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Credit Approvals';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('customer.company_name')->label('Customer'),
                Infolists\Components\TextEntry::make('branch.name')->label('Branch'),
                Infolists\Components\TextEntry::make('quotation.number')->label('Quotation'),
                Infolists\Components\TextEntry::make('requested_amount')->money('MYR'),
                Infolists\Components\TextEntry::make('reason')->columnSpanFull(),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('requester.name')->label('Requested By'),
                Infolists\Components\TextEntry::make('approver.name')->label('Decided By'),
                Infolists\Components\TextEntry::make('remarks')->columnSpanFull(),
                Infolists\Components\TextEntry::make('decided_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.company_name')->searchable(),
                Tables\Columns\TextColumn::make('branch.name'),
                Tables\Columns\TextColumn::make('quotation.number')->label('Quotation'),
                Tables\Columns\TextColumn::make('requested_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('reason')->limit(40),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (CreditApprovalRequest $record) => $record->isPending())
                    ->form([Forms\Components\Textarea::make('remarks')])
                    ->action(function (CreditApprovalRequest $record, array $data) {
                        app(DecideCreditApproval::class)->execute($record, auth()->user(), true, $data['remarks'] ?? null);
                        Notification::make()->title('Credit approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (CreditApprovalRequest $record) => $record->isPending())
                    ->form([Forms\Components\Textarea::make('remarks')->required()])
                    ->action(function (CreditApprovalRequest $record, array $data) {
                        app(DecideCreditApproval::class)->execute($record, auth()->user(), false, $data['remarks']);
                        Notification::make()->title('Credit rejected')->danger()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditApprovalRequests::route('/'),
            'view' => Pages\ViewCreditApprovalRequest::route('/{record}'),
        ];
    }
}
