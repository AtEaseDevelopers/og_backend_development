<?php

namespace App\Domains\Dispatch\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSheetTransfer extends Model
{
    protected $fillable = [
        'delivery_order_id', 'from_job_sheet_id', 'to_job_sheet_id',
        'reason', 'transferred_by',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function fromJobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class, 'from_job_sheet_id');
    }

    public function toJobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class, 'to_job_sheet_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
