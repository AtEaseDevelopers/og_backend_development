<?php

namespace App\Filament\Resources\StatementResource\Pages;

use App\Domains\Billing\Actions\GenerateStatement;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\StatementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStatement extends CreateRecord
{
    protected static string $resource = StatementResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(GenerateStatement::class)->execute(
            Customer::findOrFail($data['customer_id']),
            (int) $data['source_branch_id'],
            $data['statement_date'] ?? null
        );
    }
}
