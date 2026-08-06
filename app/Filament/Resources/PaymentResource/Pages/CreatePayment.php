<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(RecordPayment::class)->execute($data, auth()->user());
    }
}
