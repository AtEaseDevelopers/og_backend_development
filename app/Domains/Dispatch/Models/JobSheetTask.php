<?php

namespace App\Domains\Dispatch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSheetTask extends Model
{
    protected $fillable = [
        'job_sheet_id', 'delivery_order_id', 'sequence', 'route_group',
    ];

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }
}
