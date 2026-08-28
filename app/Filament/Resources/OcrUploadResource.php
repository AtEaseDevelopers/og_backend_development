<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Actions\ApproveOcrQuotation;
use App\Domains\Quotation\Actions\ProcessOcrUpload;
use App\Domains\Quotation\Models\OcrUpload;
use App\Filament\Resources\OcrUploadResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class OcrUploadResource extends Resource
{
    protected static ?string $model = OcrUpload::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'OCR Quotation Processing';

    protected static ?string $pluralModelLabel = 'OCR Uploads';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->options(fn () => Branch::query()->pluck('name', 'id'))
                ->required(),
            Forms\Components\Select::make('customer_id')
                ->options(fn () => Customer::query()->pluck('company_name', 'id'))
                ->searchable()
                ->nullable(),
            Forms\Components\FileUpload::make('upload')
                ->label('Hardcopy quotation')
                ->disk('local')
                ->directory('ocr-uploads')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->required()
                ->storeFiles(false),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('original_filename'),
                Infolists\Components\TextEntry::make('branch.name'),
                Infolists\Components\TextEntry::make('customer.company_name'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('quotation.number')->label('Quotation'),
                Infolists\Components\TextEntry::make('uploader.name')->label('Uploaded by'),
                Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewed by'),
                Infolists\Components\TextEntry::make('reviewed_at')->dateTime(),
                Infolists\Components\TextEntry::make('review_notes')->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\Section::make('Extracted data')->schema([
                Infolists\Components\TextEntry::make('extracted_data.customer_name')->label('Customer'),
                Infolists\Components\TextEntry::make('extracted_data.consignee_name')->label('Consignee'),
                Infolists\Components\TextEntry::make('extracted_data.delivery_address')->label('Address'),
                Infolists\Components\TextEntry::make('extracted_data.item_name')->label('Item'),
                Infolists\Components\TextEntry::make('extracted_data.quantity')->label('Qty'),
                Infolists\Components\TextEntry::make('extracted_data.unit_price')->label('Unit price'),
                Infolists\Components\TextEntry::make('extracted_data.line_total')->label('Line total'),
                Infolists\Components\TextEntry::make('extracted_data.confidence')->label('Confidence'),
                Infolists\Components\TextEntry::make('extracted_data.notes')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('original_filename')->searchable(),
                Tables\Columns\TextColumn::make('branch.code'),
                Tables\Columns\TextColumn::make('customer.company_name')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'pending_review' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quotation.number')->label('Quotation'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('upload')
                    ->label('Upload hardcopy')
                    ->form([
                        Forms\Components\Select::make('branch_id')
                            ->options(fn () => Branch::query()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()?->defaultBranch()?->id)
                            ->required(),
                        Forms\Components\Select::make('customer_id')
                            ->options(fn () => Customer::query()->pluck('company_name', 'id'))
                            ->searchable(),
                        Forms\Components\FileUpload::make('file')
                            ->disk('local')
                            ->directory('ocr-tmp')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->required()
                            ->storeFiles(false),
                    ])
                    ->action(function (array $data) {
                        try {
                            /** @var TemporaryUploadedFile $file */
                            $file = $data['file'];
                            $upload = app(ProcessOcrUpload::class)->execute(
                                $file,
                                auth()->user(),
                                $data['branch_id'] ?? null,
                                $data['customer_id'] ?? null
                            );
                            Notification::make()
                                ->title('Document buffered')
                                ->body('Upload #'.$upload->id.' is being extracted.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->visible(fn (OcrUpload $record) => $record->status === 'pending_review')
                    ->form([
                        Forms\Components\TextInput::make('customer_name')
                            ->default(fn (OcrUpload $record) => $record->extracted_data['customer_name'] ?? null),
                        Forms\Components\TextInput::make('consignee_name')
                            ->default(fn (OcrUpload $record) => $record->extracted_data['consignee_name'] ?? null),
                        Forms\Components\Textarea::make('delivery_address')
                            ->default(fn (OcrUpload $record) => $record->extracted_data['delivery_address'] ?? null),
                        Forms\Components\TextInput::make('item_name')
                            ->default(fn (OcrUpload $record) => $record->extracted_data['item_name'] ?? null),
                        Forms\Components\TextInput::make('quantity')->numeric()
                            ->default(fn (OcrUpload $record) => $record->extracted_data['quantity'] ?? null),
                        Forms\Components\TextInput::make('unit_price')->numeric()
                            ->default(fn (OcrUpload $record) => $record->extracted_data['unit_price'] ?? null),
                        Forms\Components\TextInput::make('line_total')->numeric()
                            ->default(fn (OcrUpload $record) => $record->extracted_data['line_total'] ?? null),
                        Forms\Components\Textarea::make('review_notes'),
                    ])
                    ->action(function (OcrUpload $record, array $data) {
                        try {
                            $upload = app(ApproveOcrQuotation::class)->execute($record, $data, auth()->user());
                            Notification::make()
                                ->title('Quotation created from OCR')
                                ->body($upload->quotation?->number)
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->visible(fn (OcrUpload $record) => $record->status === 'pending_review')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(function (OcrUpload $record, array $data) {
                        app(ApproveOcrQuotation::class)->reject($record, $data['reason'], auth()->user());
                        Notification::make()->title('OCR rejected')->warning()->send();
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
            'index' => Pages\ListOcrUploads::route('/'),
            'view' => Pages\ViewOcrUpload::route('/{record}'),
        ];
    }
}
