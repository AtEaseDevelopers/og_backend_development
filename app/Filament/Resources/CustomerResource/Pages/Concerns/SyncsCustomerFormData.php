<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;

trait SyncsCustomerFormData
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null)) {
            $data['code'] = CustomerForm::generateDebtorCode($data['company_name'] ?? null);
        }

        if (blank($data['einvoice_buyer_name'] ?? null) && filled($data['company_name'] ?? null)) {
            $data['einvoice_buyer_name'] = $data['company_name'];
        }

        if (blank($data['einvoice_tin'] ?? null) && filled($data['tin'] ?? null)) {
            $data['einvoice_tin'] = $data['tin'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $delivery = $this->getRecord()
            ->addresses()
            ->where('type', 'delivery')
            ->orderByDesc('is_default')
            ->first();

        $data['delivery_address_text'] = $delivery?->address;
        $data['delivery_postcode'] = $delivery?->postcode;
        $data['delivery_state'] = $delivery?->state;
        $data['delivery_city'] = $delivery?->city;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Customer $customer */
        $customer = $this->getRecord();
        $state = $this->form->getState();

        $this->syncPrimaryDeliveryAddress($customer, $state);
    }

    /** @param  array<string, mixed>  $state */
    private function syncPrimaryDeliveryAddress(Customer $customer, array $state): void
    {
        $addressText = trim((string) ($state['delivery_address_text'] ?? ''));

        if ($addressText === '') {
            return;
        }

        CustomerAddress::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'type' => 'delivery',
                'is_default' => true,
            ],
            [
                'label' => 'Primary delivery',
                'address' => $addressText,
                'postcode' => $state['delivery_postcode'] ?? null,
                'state' => $state['delivery_state'] ?? null,
                'city' => $state['delivery_city'] ?? null,
            ],
        );
    }
}
