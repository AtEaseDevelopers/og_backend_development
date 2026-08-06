<?php

namespace App\Filament\Resources;

use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Actions\EvaluateCreditEligibility;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Filament\Resources\QuotationResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';


    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Header')->schema([
                Forms\Components\Hidden::make('company_id')->default(fn () => \App\Support\CurrentCompany::id())->required(),
                Forms\Components\Hidden::make('branch_id')
                    ->default(fn () => \App\Support\CurrentCompany::branchId())
                    ->required(),
                Forms\Components\Placeholder::make('current_branch')
                    ->label('Company')
                    ->content(fn () => \App\Support\CurrentCompany::get()?->getFilamentName() ?? 'Select a company first'),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('salesperson_id')
                    ->relationship('salesperson', 'name')
                    ->searchable(),
                Forms\Components\Select::make('status')
                    ->options(collect(QuotationStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->label()]
                    ))
                    ->default(QuotationStatus::Draft->value)
                    ->required(),
                Forms\Components\DatePicker::make('valid_until'),
                Forms\Components\Select::make('pricing_source')
                    ->options([
                        'default' => 'Default Pricing',
                        'previous' => 'Previous Quotation',
                        'formula' => 'Formula Pricing',
                        'manual' => 'Manual Pricing',
                    ]),
                Forms\Components\TextInput::make('tax_amount')->numeric()->default(0),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),
            Forms\Components\Section::make('Destinations')->schema([
                Forms\Components\Repeater::make('destinations')
                    ->relationship()
                    ->schema([
                        Forms\Components\TextInput::make('consignee_name'),
                        Forms\Components\TextInput::make('consignee_pic'),
                        Forms\Components\TextInput::make('consignee_phone'),
                        Forms\Components\Textarea::make('address')->required()->columnSpanFull(),
                        Forms\Components\TextInput::make('postcode'),
                        Forms\Components\TextInput::make('state'),
                        Forms\Components\TextInput::make('city'),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Items')->schema([
                Forms\Components\Repeater::make('lines')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('quotation_destination_id')
                            ->label('Destination')
                            ->options(fn (?Quotation $record) => $record
                                ? $record->destinations()->pluck('address', 'id')
                                : [])
                            ->searchable(),
                        Forms\Components\TextInput::make('item_name')->required(),
                        Forms\Components\TextInput::make('uom'),
                        Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
                        Forms\Components\TextInput::make('weight')->numeric(),
                        Forms\Components\TextInput::make('dimensions'),
                        Forms\Components\TextInput::make('unit_price')->numeric()->default(0)->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $set('line_total', round(((float) $get('quantity')) * ((float) $get('unit_price')), 2));
                            }),
                        Forms\Components\TextInput::make('line_total')->numeric()->default(0)->required(),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
                Tables\Columns\TextColumn::make('customer.company_name')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(
                    fn ($state) => $state instanceof QuotationStatus ? $state->label() : $state
                ),
                Tables\Columns\TextColumn::make('total_amount')->money('MYR'),
                Tables\Columns\TextColumn::make('valid_until')->date(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(QuotationStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => $c->label()]
                    )),
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
