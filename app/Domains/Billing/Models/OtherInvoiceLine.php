<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of an Other Invoice: the item picked from Other Items, the description keyed for it and the amount. */
class OtherInvoiceLine extends Model
{
    protected $fillable = ['other_invoice_id', 'other_item_id', 'item_name', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        // the item's name is kept on the line, so the invoice reads the same after the item is renamed or removed
        static::saving(function (self $line): void {
            if ($line->other_item_id && ($line->isDirty('other_item_id') || blank($line->item_name))) {
                $line->item_name = OtherItem::query()->whereKey($line->other_item_id)->value('name') ?: $line->item_name;
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OtherInvoice::class, 'other_invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OtherItem::class, 'other_item_id');
    }
}
