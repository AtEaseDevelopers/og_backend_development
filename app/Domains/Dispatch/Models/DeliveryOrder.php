<?php

namespace App\Domains\Dispatch\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\BreakBulk;
use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\Delivery\Models\ProofOfDelivery;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\DeliveryOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryOrder extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'number', 'consignment_note_id', 'company_id', 'source_branch_id', 'job_sheet_id',
        'lorry_id', 'driver_id', 'status', 'tracking_token', 'parent_do_id',
        'is_duplicate', 'delivered_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryOrderStatus::class,
            'is_duplicate' => 'boolean',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function lorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function proofOfDelivery(): HasOne
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function failedDelivery(): HasOne
    {
        return $this->hasOne(FailedDelivery::class);
    }

    public function subsheets(): HasMany
    {
        return $this->hasMany(Subsheet::class);
    }

    public function breakBulks(): HasMany
    {
        return $this->hasMany(BreakBulk::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_do_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'parent_do_id');
    }
}
