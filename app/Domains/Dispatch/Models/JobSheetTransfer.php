<?php

namespace App\Domains\Dispatch\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit record of a task moving between job sheets / lorries, including in-route
 * lorry changes (section J): original and new lorry + driver, time, place and reason.
 */
class JobSheetTransfer extends Model
{
    protected $fillable = [
        'delivery_order_id', 'consignment_note_id', 'from_job_sheet_id', 'to_job_sheet_id',
        'from_lorry_id', 'to_lorry_id', 'from_driver_id', 'to_driver_id',
        'in_route', 'handover_at', 'handover_location', 'reason', 'transferred_by',
    ];

    protected function casts(): array
    {
        return [
            'in_route' => 'boolean',
            'handover_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function fromJobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class, 'from_job_sheet_id');
    }

    public function toJobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class, 'to_job_sheet_id');
    }

    public function fromLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'from_lorry_id');
    }

    public function toLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'to_lorry_id');
    }

    public function fromDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'from_driver_id');
    }

    public function toDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'to_driver_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
