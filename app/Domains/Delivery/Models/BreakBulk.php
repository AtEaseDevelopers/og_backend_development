<?php

namespace App\Domains\Delivery\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BreakBulk extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'delivery_order_id', 'job_sheet_id', 'consignment_note_id',
        'original_driver_id', 'original_lorry_id', 'requested_by_driver_id', 'created_by',
        'replacement_driver_id', 'replacement_lorry_id', 'subcontractor_id',
        'location', 'reason', 'handover_status', 'status', 'revoke_reason',
        'photo_paths', 'latitude', 'longitude',
        'released_at', 'collected_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'released_at' => 'datetime',
            'collected_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function originalDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'original_driver_id');
    }

    public function originalLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'original_lorry_id');
    }

    public function replacementDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'replacement_driver_id');
    }

    public function replacementLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'replacement_lorry_id');
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function requestedByDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'requested_by_driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
