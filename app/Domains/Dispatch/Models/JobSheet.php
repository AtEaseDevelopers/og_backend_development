<?php

namespace App\Domains\Dispatch\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\JobSheetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JobSheet extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'number', 'company_id', 'operating_branch_id', 'lorry_id', 'driver_id',
        'operating_date', 'status', 'is_shared_dispatch', 'checked_in_at',
        'trip_no', 'checked_in_lorry_id', 'planned_departure_at', 'departed_at', 'arrived_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobSheetStatus::class,
            'operating_date' => 'date',
            'checked_in_at' => 'datetime',
            'is_shared_dispatch' => 'boolean',
            'trip_no' => 'integer',
            'planned_departure_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function checkedInLorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class, 'checked_in_lorry_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(JobSheetTransfer::class, 'to_job_sheet_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(JobSheetTransfer::class, 'from_job_sheet_id');
    }

    public function tripLabel(): string
    {
        return 'Trip '.($this->trip_no ?: 1);
    }

    public function isOpen(): bool
    {
        return $this->status !== JobSheetStatus::Completed;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function operatingBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'operating_branch_id');
    }

    public function lorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(JobSheetTask::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function subsheets(): HasMany
    {
        return $this->hasMany(Subsheet::class);
    }
}
