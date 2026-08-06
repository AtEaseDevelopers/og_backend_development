<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CreditApprovalRequest extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'customer_id', 'company_id', 'branch_id', 'quotation_id', 'reason', 'requested_amount',
        'trigger_details', 'status', 'requested_by', 'approved_by', 'remarks', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'requested_amount' => 'decimal:2',
            'trigger_details' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
