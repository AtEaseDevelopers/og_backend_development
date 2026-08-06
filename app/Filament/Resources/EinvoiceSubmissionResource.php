<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Integration\Actions\SubmitEinvoice;
use App\Domains\Integration\Actions\UpdateEinvoiceBuyerInfo;
use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Filament\Resources\EinvoiceSubmissionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class EinvoiceSubmissionResource extends Resource
{
    protected static ?string $model = EinvoiceSubmission::class;

    protected static bool $isScopedToTenant = false;


    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'MyInvois e-Invoice';

    protected static ?int $navigationSort = 61;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('invoice_id')
                ->label('Invoice')
                ->options(fn () => Invoice::query()
                    ->orderByDesc('id')->limit(100)
                    ->get()
                    ->mapWithKeys(fn (Invoice $i) => [
                        $i->id => $i->number.' — '.$i->customer?->company_name,
                    ]))
                ->searchable()
                ->required()
                ->disabled(fn (?EinvoiceSubmission $record) => (bool) $record),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('invoice.number')->label('Invoice'),
                Infolists\Components\TextEntry::make('invoice.customer.company_name')->label('Customer'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('submission_mode'),
                Infolists\Components\TextEntry::make('uuid')->copyable(),
                Infolists\Components\TextEntry::make('validated_pdf_path')->label('PDF path'),
                Infolists\Components\TextEntry::make('buyer_token')
                    ->label('Buyer form URL')
                    ->formatStateUsing(fn (?string $state, EinvoiceSubmission $record) => $state ? $record->publicBuyerUrl() : null)
                    ->copyable(),
                Infolists\Components\TextEntry::make('submitted_at')->dateTime(),
                Infolists\Components\TextEntry::make('email_sent_at')->dateTime(),
                Infolists\Components\TextEntry::make('retry_count'),
            ])->columns(3),
            Infolists\Components\Section::make('Buyer info')->schema([
                Infolists\Components\TextEntry::make('buyer_info.name')->label('Name'),
                Infolists\Components\TextEntry::make('buyer_info.tin')->label('TIN'),
                Infolists\Components\TextEntry::make('buyer_info.brn')->label('BRN'),
                Infolists\Components\TextEntry::make('buyer_info.address')->label('Address')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.number')->searchable()->label('Invoice'),
                Tables\Columns\TextColumn::make('invoice.sourceBranch.code')->label('Branch'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'failed' => 'danger',
                        'ready', 'pending_buyer' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('uuid')->limit(20)->copyable(),
                Tables\Columns\TextColumn::make('submission_mode'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
                Tables\Columns\TextColumn::make('retry_count'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('prepare')
                    ->label('Prepare from invoice')
                    ->form([
                        Forms\Components\Select::make('invoice_id')
                            ->options(fn () => Invoice::query()
                                ->whereDoesntHave('einvoiceSubmission', fn ($q) => $q->where('status', 'valid'))
                                ->orderByDesc('id')->limit(50)
                                ->pluck('number', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $sub = app(SubmitEinvoice::class)->ensureBuyerLink(Invoice::findOrFail($data['invoice_id']));
                        Notification::make()
                            ->title('Buyer link ready')
                            ->body($sub->publicBuyerUrl())
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('edit_buyer')
                    ->icon('heroicon-o-user')
                    ->form([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('tin'),
                        Forms\Components\TextInput::make('brn'),
                        Forms\Components\TextInput::make('id_type')->default('BRN'),
                        Forms\Components\TextInput::make('id_value'),
                        Forms\Components\Textarea::make('address'),
                    ])
                    ->fillForm(fn (EinvoiceSubmission $record) => $record->buyer_info ?? [])
                    ->action(function (EinvoiceSubmission $record, array $data) {
                        app(UpdateEinvoiceBuyerInfo::class)->execute($record, $data);
                        Notification::make()->title('Buyer info updated')->success()->send();
                    }),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (EinvoiceSubmission $record) => $record->status !== 'valid')
                    ->action(function (EinvoiceSubmission $record) {
                        try {
                            $sub = app(SubmitEinvoice::class)->execute($record->invoice, auth()->user());
                            Notification::make()
                                ->title($sub->status === 'valid' ? 'MyInvois valid' : 'Submit failed')
                                ->body($sub->uuid ?? 'Check response payload')
                                ->color($sub->status === 'valid' ? 'success' : 'danger')
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['invoice.customer', 'invoice.sourceBranch']);
        $companyId = \App\Support\CurrentCompany::id();
        if ($companyId) {
            $query->whereHas('invoice', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEinvoiceSubmissions::route('/'),
            'view' => Pages\ViewEinvoiceSubmission::route('/{record}'),
        ];
    }
}
