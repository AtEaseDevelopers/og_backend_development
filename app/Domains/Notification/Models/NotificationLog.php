<?php

namespace App\Domains\Notification\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Message / email history (recipient, type, channel, time, delivery status, related document).
 */
class NotificationLog extends Model
{
    use BelongsToCompany;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_SYSTEM = 'system';

    public const STATUS_SENT = 'sent';

    public const STATUS_READY = 'ready';      // WhatsApp link prepared, sent manually by staff

    public const STATUS_SKIPPED = 'skipped';  // channel disabled or recipient contact missing

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id', 'branch_id', 'event', 'channel', 'status',
        'recipient_type', 'recipient_name', 'recipient_contact',
        'subject', 'message', 'whatsapp_url', 'error',
        'notifiable_type', 'notifiable_id', 'sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
