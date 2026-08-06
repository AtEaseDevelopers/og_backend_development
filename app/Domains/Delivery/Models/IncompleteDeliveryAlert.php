<?php

namespace App\Domains\Delivery\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncompleteDeliveryAlert extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'alert_date', 'delivery_order_id', 'job_sheet_id', 'company_id', 'branch_id',
        'status', 'notified_at', 'acknowledged_by', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'alert_date' => 'date',
            'notified_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
