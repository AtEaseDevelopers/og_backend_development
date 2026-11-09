<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPic extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'phone', 'email', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // a blank row (no PIC name, no contact number) is not saved (user, 10 Nov 2026)
        static::creating(fn (self $row) => filled(trim((string) $row->name)) || filled(trim((string) $row->phone)));

        // the customer's attention / phone follow the default PIC (user, 10 Nov 2026), then its consignor / consignee
        // row may take its address / PIC from the default one (user, 25 Oct 2026)
        $sync = function (self $row): void {
            if ($customer = Customer::query()->find($row->customer_id)) {
                $customer->syncContactFromPics();
                ConsignorConsignee::syncFromCustomer($customer->fresh());
            }
        };
        static::saved($sync);
        static::deleted($sync);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
