<?php

namespace App\Domains\Quotation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    protected $fillable = [
        'quotation_id', 'quotation_destination_id', 'item_name', 'uom',
        'quantity', 'weight', 'dimensions', 'unit_price', 'line_total', 'handling_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(QuotationDestination::class, 'quotation_destination_id');
    }
}
