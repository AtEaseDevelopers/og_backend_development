<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'type', 'label', 'address', 'postcode',
        'state', 'city', 'google_maps_url', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // the customer's consignor / consignee row may take its address / PIC from the default one (user, 25 Oct 2026)
        $sync = fn (self $row) => $row->customer && ConsignorConsignee::syncFromCustomer($row->customer);
        static::saved($sync);
        static::deleted($sync);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
