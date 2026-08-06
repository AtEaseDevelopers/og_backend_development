<?php

namespace App\Domains\Quotation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationDestination extends Model
{
    protected $fillable = [
        'quotation_id', 'sequence', 'consignee_name', 'consignee_pic',
        'consignee_phone', 'address', 'postcode', 'state', 'city', 'google_maps_url',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }
}
