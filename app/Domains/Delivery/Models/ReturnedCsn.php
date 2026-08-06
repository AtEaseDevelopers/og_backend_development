<?php

namespace App\Domains\Delivery\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnedCsn extends Model
{
    protected $fillable = [
        'consignment_note_id', 'delivery_order_id', 'job_sheet_id',
        'returned_by_driver_id', 'received_by', 'scan_method', 'status',
        'is_signed', 'is_stamped', 'returned_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_signed' => 'boolean',
            'is_stamped' => 'boolean',
            'returned_at' => 'datetime',
        ];
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function returnedByDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'returned_by_driver_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
