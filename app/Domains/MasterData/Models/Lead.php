<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead (master data): a possible customer's name, PIC, contact number, email, billing address and address. Quoted
 * on Create quotation; it becomes a customer (customer_id) when its quotation is made into an order.
 */
class Lead extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'pic_name', 'contact', 'email', 'billing_address', 'address', 'customer_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
