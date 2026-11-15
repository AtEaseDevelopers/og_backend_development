<?php

namespace App\Filament\Resources;

use App\Domains\Billing\Models\OtherInvoice;
use App\Domains\Billing\Models\OtherItem;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\OtherInvoiceResource\Pages;
use App\Support\CurrentCompany;
use App\Support\QuotationMatrix;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Billing → Other Invoices (client + user, 16 Nov 2026): an invoice for charges that are not from a consignment note.
 * The customer, the date, then lines of an item (Master Data → Other Items), a description and an amount.
 */
class OtherInvoiceResource extends Resource
{
    use \App\Filament\Concerns\HasModulePermissions;

    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $model = OtherInvoice::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'other-invoices';

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Other Invoices';

    protected static ?string $modelLabel = 'other invoice';

    protected static ?string $pluralModelLabel = 'Other Invoices';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('customer')->when(CurrentCompany::id(), fn (Builder $q, $id) => $q->where('company_id', $id));
    }

    private static function sstRate(): string
    {
        return rtrim(rtrim(number_format(QuotationMatrix::sstRate(), 2, '.', ''), '0'), '.');
    }

    /** Sub total, service tax and total of the lines as keyed, before saving. @return array{0: float, 1: float, 2: float} */
    private static function totals(Get $get): array
    {
        $subtotal = round(collect($get('lines') ?? [])->sum(fn ($line) => (float) ($line['amount'] ?? 0)), 2);
        $tax = $get('charges_sst') ? round($subtotal * QuotationMatrix::sstRate() / 100, 2) : 0.0;

        return [$subtotal, $tax, round($subtotal + $tax, 2)];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CurrentCompany::id()),
            Forms\Components\Hidden::make('branch_id')->default(fn () => CurrentCompany::branchId()),
            Forms\Components\Section::make('Other invoice')->icon('heroicon-o-document-plus')->schema([
                Forms\Components\Select::make('customer_id')->label('Customer')
                    ->options(fn () => Customer::query()->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))->where('status', 'active')->orderBy('company_name')
                        ->get(['id', 'code', 'company_name'])->mapWithKeys(fn (Customer $c) => [$c->id => trim(($c->code ? $c->code.' — ' : '').$c->company_name)]))
                    ->searchable()
                    ->required()
                    ->columnSpan(5),
                Forms\Components\DatePicker::make('invoice_date')->label('Invoice date')->default(fn () => now()->toDateString())->required()->live()->columnSpan(2),
                Forms\Components\Placeholder::make('number_label')->label('Invoice no.')
                    // the number the invoice will get is shown before saving (user, 16 Nov 2026): the next one of the
                    // invoice date's month
                    ->content(fn (?OtherInvoice $record, Get $get) => new \Illuminate\Support\HtmlString('<span style="font-weight:600;font-size:1rem">'
                        .e($record?->number ?? OtherInvoice::nextNumber(filled($get('invoice_date')) ? \Illuminate\Support\Carbon::parse($get('invoice_date')) : now())).'</span>'))
                    ->columnSpan(3),
                Forms\Components\Toggle::make('charges_sst')->label(fn () => 'Charge service tax '.static::sstRate().'%')->default(false)->inline(false)->live()->columnSpan(2),
            ])->columns(12)->compact(),
            Forms\Components\Section::make('Items')->icon('heroicon-o-rectangle-stack')->schema([
                // the lines as a table (user, 16 Nov 2026): one header row, then a compact row per item with its remove
                // button at the right — no card and no label per line
                Forms\Components\Placeholder::make('lines_head')->hiddenLabel()->columnSpanFull()
                    ->content(new \Illuminate\Support\HtmlString(<<<'HTML'
                        <style>
                            .og-oi-head { display: grid; grid-template-columns: 3fr 7fr 2fr; gap: .75rem; padding: .45rem 2.75rem .45rem .75rem; margin-bottom: -1.5rem;
                                font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139);
                                background: rgb(248 250 252); border: 1px solid rgb(229 231 235); border-bottom: 0; border-radius: .6rem .6rem 0 0; }
                            .og-oi-table > ul { border: 1px solid rgb(229 231 235); border-radius: 0 0 .6rem .6rem; }
                            .og-oi-table > ul > div { gap: 0 !important; }
                            .og-oi-table li.fi-fo-repeater-item { position: relative; border-radius: 0; box-shadow: none; --tw-ring-shadow: 0 0 #0000; --tw-ring-offset-shadow: 0 0 #0000; border-top: 1px solid rgb(241 245 249); }
                            .og-oi-table li.fi-fo-repeater-item:first-child { border-top: 0; }
                            .og-oi-table .fi-fo-repeater-item-header { position: absolute; right: .35rem; top: 50%; transform: translateY(-50%); padding: 0; border: 0; z-index: 1; }
                            .og-oi-table .fi-fo-repeater-item-content { padding: .4rem 2.75rem .4rem .75rem; border-top: 0 !important; }
                            .og-oi-table .fi-fo-repeater-item-content .fi-fo-component-ctn { gap: .75rem; }
                            .og-oi-table .fi-fo-repeater-item-content .fi-fo-field-wrp-label { display: none; }
                            .dark .og-oi-head { background: rgb(31 41 55); border-color: rgb(55 65 81); color: rgb(148 163 184); }
                            .dark .og-oi-table > ul { border-color: rgb(55 65 81); }
                            .dark .og-oi-table li.fi-fo-repeater-item { border-top-color: rgb(55 65 81); }
                        </style>
                        <div class="og-oi-head"><span>Item</span><span>Description</span><span>Amount (RM)</span></div>
                        HTML)),
                Forms\Components\Repeater::make('lines')
                    ->hiddenLabel()
                    ->extraAttributes(['class' => 'og-oi-table'])
                    ->relationship('lines')
                    ->schema([
                        Forms\Components\Select::make('other_item_id')->label('Item')->placeholder('Select item')
                            ->options(fn (?string $state) => OtherItem::query()->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                                ->where(fn ($q) => $q->where('is_active', true)->when($state, fn ($q) => $q->orWhere('id', $state)))->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpan(3),
                        Forms\Components\TextInput::make('description')->label('Description')->maxLength(500)->placeholder('e.g. DIESEL LORRY NO. ABC1234 OCT 26')->columnSpan(7),
                        Forms\Components\TextInput::make('amount')->label('Amount (RM)')->numeric()->minValue(0)->required()->live(onBlur: true)->columnSpan(2),
                    ])
                    ->columns(12)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable(false)
                    ->addActionLabel('+ Add item')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('remarks')->label('Remarks')->rows(2)->columnSpan(7),
                Forms\Components\Placeholder::make('totals')->label('Total')
                    ->content(function (Get $get) {
                        [$subtotal, $tax, $total] = static::totals($get);

                        return new \Illuminate\Support\HtmlString(
                            '<div style="display:grid;grid-template-columns:1fr auto;gap:.15rem 1.5rem;font-size:.875rem">'
                            .'<span>Sub total (excluding tax)</span><span style="text-align:right">'.number_format($subtotal, 2).'</span>'
                            .'<span>Service tax '.static::sstRate().'%</span><span style="text-align:right">'.number_format($tax, 2).'</span>'
                            .'<strong>Total</strong><strong style="text-align:right">RM '.number_format($total, 2).'</strong></div>'
                        );
                    })->columnSpan(5),
            ])->columns(12)->compact(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Invoice no.')->searchable()->sortable()->weight('semibold')->copyable(),
                Tables\Columns\TextColumn::make('invoice_date')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->description(fn (OtherInvoice $record) => $record->customer?->code)->searchable()->wrap(),
                Tables\Columns\TextColumn::make('items')->label('Items')
                    ->state(fn (OtherInvoice $record) => $record->lines->map(fn ($line) => trim($line->item_name.(filled($line->description) ? ' — '.$line->description : '')))->implode('; '))
                    ->limit(70)->tooltip(fn (OtherInvoice $record) => $record->lines->map(fn ($line) => trim($line->item_name.(filled($line->description) ? ' — '.$line->description : '')))->implode('; ')),
                Tables\Columns\TextColumn::make('total_amount')->label('Total (RM)')->numeric(2)->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->placeholder('—')->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['lines', 'creator']))
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->url(fn (OtherInvoice $record) => static::pdfUrl($record))->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function pdfUrl(OtherInvoice $record): string
    {
        return route('filament.admin.other-invoices.pdf', ['tenant' => Filament::getTenant(), 'otherInvoice' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtherInvoices::route('/'),
            'create' => Pages\CreateOtherInvoice::route('/create'),
            'edit' => Pages\EditOtherInvoice::route('/{record}/edit'),
        ];
    }
}
