<?php

namespace App\Domains\Integration\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'source_branch_id', 'integration', 'document_type', 'document_id',
        'external_ref', 'status', 'retry_count', 'error_message', 'payload',
        'synced_by', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function syncedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synced_by');
    }
}
