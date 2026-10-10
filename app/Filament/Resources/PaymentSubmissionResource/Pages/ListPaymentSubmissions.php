<?php

namespace App\Filament\Resources\PaymentSubmissionResource\Pages;

use App\Domains\Billing\Actions\SubmitPaymentEvidence;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Enums\QuotationStatus;
use App\Filament\Resources\PaymentSubmissionResource;
use App\Support\CurrentCompany;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListPaymentSubmissions extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = PaymentSubmissionResource::class;

    public function getHeading(): string
    {
        return 'Payment Review';
    }

    public function getSubheading(): ?string
    {
        return 'Customer payment proofs and counter payments awaiting verification / approval.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recordCounterPayment')
                ->label('Record counter payment')
                ->icon('heroicon-o-banknotes')
                ->form([
                    Forms\Components\Select::make('quotation_id')
                        ->label('Order')
                        ->options(fn () => Quotation::query()
                            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
                            ->whereIn('status', [QuotationStatus::Accepted->value, QuotationStatus::PendingApproval->value, QuotationStatus::Confirmed->value])
                            ->orderByDesc('id')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (Quotation $q) => [$q->id => $q->number.' — '.$q->customer?->company_name.' (outstanding RM '.number_format($q->outstandingAmount(), 2).')']))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->prefix('RM')->required(),
                    Forms\Components\Select::make('method')->options(PaymentMethod::options())->default(PaymentMethod::Counter->value)->required(),
                    Forms\Components\DatePicker::make('payment_date')->default(now()),
                    Forms\Components\TextInput::make('reference'),
                    Forms\Components\TextInput::make('bank_account')->label('Bank / account'),
                    Forms\Components\Textarea::make('remarks'),
                ])
                ->action(function (array $data) {
                    try {
                        $quotation = Quotation::query()->findOrFail($data['quotation_id']);
                        app(SubmitPaymentEvidence::class)->execute($quotation, $data, auth()->user(), 'counter');
                        Notification::make()->title('Counter payment recorded — pending approval')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
