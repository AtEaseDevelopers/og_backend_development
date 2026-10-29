<?php

namespace App\Domains\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A portal login's access to one customer account (BRN): pending until Admin approves it. Many logins can share
 * one BRN and one login can hold many BRNs (section P).
 */
class CustomerPortalLink extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'customer_user';

    protected $fillable = ['customer_id', 'user_id', 'status', 'reviewed_by', 'reviewed_at', 'review_remarks'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
