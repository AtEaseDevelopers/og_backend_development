<?php

namespace App\Domains\Dispatch\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitSharingTransaction extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'source_branch_id', 'delivery_order_id', 'consignment_note_id',
        'assisting_driver_id', 'main_driver_id', 'psi_amount', 'pso_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'psi_amount' => 'decimal:2',
            'pso_amount' => 'decimal:2',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function assistingDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assisting_driver_id');
    }

    public function mainDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'main_driver_id');
    }
}
