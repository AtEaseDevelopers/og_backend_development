<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;

trait SyncsCustomerFormData
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null)) {
            $data['code'] = CustomerForm::generateDebtorCode($data['company_name'] ?? null);
        }

        return $data;
    }

    /** Debtor type shows what the customer is (an older customer without one: Credit when a credit customer, else Cash). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! array_key_exists((string) ($data['default_order_type'] ?? ''), CustomerForm::debtorTypes())) {
            $data['default_order_type'] = ($data['is_credit'] ?? false) ? 'term' : 'cash';
        }

        return $data;
    }

    /** No delivery address on the customer any more (user, 16 Nov 2026: it follows the consignor of the order). */
    protected function afterSave(): void
    {
        //
    }

    /**
     * An empty attention row is not saved (CustomerPic refuses it), which leaves "false" in the list the form keeps on
     * the record, and the page then cannot be sent back to the browser after Create / Save. The list is dropped before
     * each reply (Livewire calls this for the trait) and read again from the table when it is next needed.
     */
    public function dehydrateSyncsCustomerFormData(): void
    {
        if (($this->record ?? null) instanceof \Illuminate\Database\Eloquent\Model) {
            $this->record->unsetRelation('pics');
        }
    }
}
