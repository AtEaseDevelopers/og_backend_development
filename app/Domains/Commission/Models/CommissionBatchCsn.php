<?php

namespace App\Domains\Commission\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A returned CSN taken into a commission batch (each CSN is in one batch only). */
class CommissionBatchCsn extends Model
{
    protected $fillable = ['commission_batch_id', 'consignment_note_id', 'returned_at', 'amount'];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommissionBatch::class, 'commission_batch_id');
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }
}
