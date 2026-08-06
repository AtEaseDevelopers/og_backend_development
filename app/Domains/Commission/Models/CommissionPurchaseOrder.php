<?php

namespace App\Domains\Commission\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPurchaseOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'po_number', 'pi_number', 'commission_slip_id', 'company_id', 'source_branch_id',
        'driver_id', 'amount', 'status', 'autocount_sync_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(CommissionSlip::class, 'commission_slip_id');
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
