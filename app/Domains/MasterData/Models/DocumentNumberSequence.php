<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberSequence extends Model
{
    protected $fillable = ['branch_id', 'document_type', 'period', 'last_number'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
