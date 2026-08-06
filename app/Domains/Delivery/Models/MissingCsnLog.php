<?php

namespace App\Domains\Delivery\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingCsnLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'consignment_note_id', 'company_id', 'source_branch_id', 'delivery_order_id',
        'status', 'marked_missing_at', 'follow_up_remarks', 'investigation_status',
        'returned_csn_id', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'marked_missing_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function returnedCsn(): BelongsTo
    {
        return $this->belongsTo(ReturnedCsn::class, 'returned_csn_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending_return', 'missing'], true);
    }
}
