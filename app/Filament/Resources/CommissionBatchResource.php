<?php

namespace App\Filament\Resources;

use App\Domains\Commission\Actions\ConfirmCommissionBatch;
use App\Domains\Commission\Actions\GenerateCommissionBatch;
use App\Domains\Commission\Actions\GenerateCommissionPurchaseOrders;
use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\CommissionBatchResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class CommissionBatchResource extends Resource
{
    protected static ?string $model = CommissionBatch::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Commission';

    protected static ?string $navigationLabel = 'Commission Batches';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('number'),
                Infolists\Components\TextEntry::make('sourceBranch.name')->label('Source branch'),
                Infolists\Components\TextEntry::make('month'),
                Infolists\Components\TextEntry::make('cutoff_date')->date(),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('confirmedBy.name')->label('Confirmed by'),
                Infolists\Components\TextEntry::make('confirmed_at')->dateTime(),
                Infolists\Components\TextEntry::make('notes')->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('slips')
                ->schema([
                    Infolists\Components\TextEntry::make('number'),
                    Infolists\Components\TextEntry::make('driver.name')->label('Driver'),
                    Infolists\Components\TextEntry::make('system_amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('final_amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('psi_amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('pso_amount')->money('MYR'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable(),
                Tables\Columns\TextColumn::make('sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('month')->sortable(),
                Tables\Columns\TextColumn::make('cutoff_date')->date(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'po_generated' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('slips_count')->counts('slips')->label('Slips'),
                Tables\Columns\TextColumn::make('confirmed_at')->dateTime(),
            ])
            ->defaultSort('month', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate / rebuild batch')
                    ->icon('heroicon-o-sparkles')
                    ->form([
                        Forms\Components\Placeholder::make('branch_label')
                            ->label('Source branch')
                            ->content(fn () => \App\Support\CurrentBranch::get()?->getFilamentName() ?? '—'),
                        Forms\Components\TextInput::make('month')
                            ->label('Month (YYYY-MM)')
                            ->default(now()->format('Y-m'))
                            ->required(),
                        Forms\Components\DatePicker::make('cutoff_date')
                            ->label('Manual cutoff (carry-forward)')
                            ->default(now()->endOfMonth()),
                    ])
                    ->action(function (array $data) {
                        try {
                            $branch = \App\Support\CurrentBranch::get()
                                ?? Branch::findOrFail(auth()->user()?->defaultBranch()?->id);
                            $batch = app(GenerateCommissionBatch::class)->execute(
                                $branch,
                                $data['month'],
                                auth()->user(),
                                isset($data['cutoff_date']) ? \Illuminate\Support\Carbon::parse($data['cutoff_date']) : null,
                            );
                            Notification::make()
                                ->title('Commission batch ready')
                                ->body($batch->number.' — '.$batch->slips()->count().' slip(s)')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('confirm')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->visible(fn (CommissionBatch $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (CommissionBatch $record) {
                        try {
                            app(ConfirmCommissionBatch::class)->execute($record, auth()->user());
                            Notification::make()->title('Batch confirmed & locked')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('generate_po')
                    ->label('Generate PO/PI')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (CommissionBatch $record) => in_array($record->status, ['confirmed', 'po_generated'], true))
                    ->action(function (CommissionBatch $record) {
                        try {
                            $pos = app(GenerateCommissionPurchaseOrders::class)->execute($record);
                            Notification::make()
                                ->title('PO/PI generated')
                                ->body($pos->count().' purchase order(s)')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
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
            'index' => Pages\ListCommissionBatches::route('/'),
            'view' => Pages\ViewCommissionBatch::route('/{record}'),
        ];
    }
}
