<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'source_branch_id', 'customer_id', 'consignment_note_id', 'invoice_id',
        'delivery_order_id', 'driver_id', 'method', 'amount', 'expected_amount',
        'shortage_amount', 'reference', 'status', 'reconciliation_status',
        'slip_path', 'remarks', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
