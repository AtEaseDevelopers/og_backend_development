<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'number', 'source_branch_id', 'payment_id', 'customer_id',
        'amount', 'type', 'autocount_sync_status',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Used by the receipt PDF (DocumentPdf::receipt): without it the portal's receipt download failed. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\MasterData\Models\Customer::class);
    }
}
