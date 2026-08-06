<?php

namespace App\Domains\Dispatch\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\Subcontractor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subsheet extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'job_sheet_id', 'delivery_order_id', 'consignment_note_id',
        'transfer_code', 'task_type', 'notes',
        'main_driver_id', 'main_lorry_id', 'sub_driver_id',
        'sub_lorry_id', 'subcontractor_id', 'segment_route', 'handover_status',
        'psi_amount', 'pso_amount', 'profit_sharing_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'psi_amount' => 'decimal:2',
            'pso_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function mainDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'main_driver_id');
    }

    public function mainLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'main_lorry_id');
    }

    public function subDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'sub_driver_id');
    }

    public function subLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'sub_lorry_id');
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function profitSharingTransaction(): BelongsTo
    {
        return $this->belongsTo(ProfitSharingTransaction::class);
    }
}
