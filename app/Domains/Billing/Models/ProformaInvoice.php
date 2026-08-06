<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'number', 'consignment_note_id', 'company_id', 'source_branch_id', 'total_amount', 'status',
    ];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }
}
