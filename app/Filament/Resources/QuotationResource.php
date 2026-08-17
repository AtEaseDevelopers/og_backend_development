<?php

namespace App\Filament\Resources;

use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Actions\EvaluateCreditEligibility;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\Schemas\QuotationForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return QuotationForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company.name')->label('Company')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('salesperson.name')->label('Salesperson')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(
                    fn ($state) => $state instanceof QuotationStatus ? $state->label() : $state
                ),
                Tables\Columns\TextColumn::make('pricing_source')
                    ->label('Pricing')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR')->sortable(),
                Tables\Columns\TextColumn::make('valid_until')->date()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('salesperson_id')
                    ->label('Salesperson')
                    ->relationship('salesperson', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(QuotationStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->label()]
                    )),
                Tables\Filters\SelectFilter::make('pricing_source')
                    ->label('Pricing source')
                    ->options([
                        'default' => 'Default Pricing',
                        'special' => 'Customer Special',
                        'previous' => 'Previous Quotation',
                        'formula' => 'Formula Pricing',
                        'manual' => 'Manual Pricing',
                        'ocr' => 'OCR',
                    ]),
                Tables\Filters\Filter::make('valid_until')
                    ->label('Valid until')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('valid_until', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('valid_until', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->label('Created at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('total_amount')
                    ->label('Total amount')
                    ->form([
                        Forms\Components\TextInput::make('min')
                            ->label('Min (MYR)')
                            ->numeric(),
                        Forms\Components\TextInput::make('max')
                            ->label('Max (MYR)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min'] ?? null,
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max'] ?? null,
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
                            );
                    }),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('previewPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Quotation $record): string => route('filament.admin.quotations.pdf', [
                        'tenant' => \Filament\Facades\Filament::getTenant(),
                        'quotation' => $record,
                    ]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Quotation $record) => in_array($record->status, [
                        QuotationStatus::Draft, QuotationStatus::Sent, QuotationStatus::Accepted,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (Quotation $record) {
                        $result = app(EvaluateCreditEligibility::class)->execute($record, auth()->user());

                        if (! $result['allowed']) {
                            Notification::make()
                                ->title('Pending Branch Manager credit approval')
                                ->body(implode("\n", $result['reasons']))
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => QuotationStatus::Confirmed,
                            'confirmed_at' => now(),
                        ]);
                        Notification::make()->title('Quotation confirmed')->success()->send();
                    }),
                Tables\Actions\Action::make('convert')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Quotation $record) => in_array($record->status, [
                        QuotationStatus::Confirmed, QuotationStatus::Accepted,
                    ], true))
                    ->form([
                        Forms\Components\Select::make('billing_type')
                            ->options([
                                'cash_bill' => 'Cash Bill',
                                'cod' => 'COD / Advance Taken',
                                'term' => 'Term Billing',
                            ])
                            ->default('cash_bill')
                            ->required()
                            ->live(),
                    ])
                    ->action(function (Quotation $record, array $data) {
                        if ($data['billing_type'] === 'term' && $record->customer?->is_credit) {
                            $result = app(EvaluateCreditEligibility::class)->execute(
                                $record,
                                auth()->user(),
                                createRequest: false
                            );
                            if (! $result['allowed']) {
                                Notification::make()
                                    ->title('Term conversion blocked — credit approval required')
                                    ->body(implode("\n", $result['reasons']))
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $notes = app(ConvertQuotationToCsns::class)->execute(
                            $record,
                            auth()->user(),
                            $data['billing_type']
                        );
                        Notification::make()
                            ->title('Converted to '.$notes->count().' CSN(s)')
                            ->success()
                            ->send();
                    }),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
