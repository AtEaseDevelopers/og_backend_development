<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Enums\OrderType;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enquiry / Order Form intake. One enquiry is owned by exactly one salesperson and may
 * produce several order records (quotations) that all inherit that owner (section A/B).
 */
class PortalEnquiry extends Model
{
    use BelongsToCompany;

    public const SOURCE_SALESPERSON_LINK = 'salesperson_link';

    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'customer_id', 'company_id', 'branch_id', 'user_id', 'reference_no', 'order_number', 'received_through', 'pickup_address',
        'pickup_maps_url', 'preferred_delivery_date', 'special_requirements',
        'status', 'quotation_id', 'payload',
        'salesperson_id', 'salesperson_locked', 'source', 'sa_location_id',
        'order_type', 'service_type', 'payment_method', 'customer_do_number', 'attachments',
        'locked_by', 'locked_at', 'lock_heartbeat_at', 'attended_by', 'attended_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_delivery_date' => 'date',
            'payload' => 'array',
            'attachments' => 'array',
            'status' => PortalEnquiryStatus::class,
            'order_type' => OrderType::class,
            'service_type' => \App\Enums\ServiceType::class,
            'salesperson_locked' => 'boolean',
            'locked_at' => 'datetime',
            'lock_heartbeat_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Primary (first) order created from this enquiry. */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Every order record created from this enquiry. */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'portal_enquiry_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function saLocation(): BelongsTo
    {
        return $this->belongsTo(SaLocation::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Temporary edit lock (section A: 2-second availability check)
    |--------------------------------------------------------------------------
    */

    public function lockTimeoutSeconds(): int
    {
        return max(4, SystemSettings::int(SystemSettings::ENQUIRY_LOCK_SECONDS));
    }

    /** True when another user currently holds a live lock on this enquiry. */
    public function isLockedByOther(?User $user): bool
    {
        if (! $this->locked_by || ! $this->lock_heartbeat_at) {
            return false;
        }

        if ($user && (int) $this->locked_by === (int) $user->id) {
            return false;
        }

        return $this->lock_heartbeat_at->gt(now()->subSeconds($this->lockTimeoutSeconds()));
    }

    /** Acquire (or refresh) the lock for $user; returns false when someone else holds it. */
    public function acquireLock(User $user): bool
    {
        $fresh = static::query()->lockForUpdate()->find($this->id) ?? $this;

        if ($fresh->isLockedByOther($user)) {
            $this->setRawAttributes($fresh->getAttributes(), true);

            return false;
        }

        $fresh->forceFill([
            'locked_by' => $user->id,
            'locked_at' => $fresh->locked_by === $user->id ? ($fresh->locked_at ?? now()) : now(),
            'lock_heartbeat_at' => now(),
        ])->save();

        $this->setRawAttributes($fresh->getAttributes(), true);

        return true;
    }

    public function heartbeat(User $user): bool
    {
        if ((int) $this->locked_by !== (int) $user->id) {
            return $this->acquireLock($user);
        }

        $this->forceFill(['lock_heartbeat_at' => now()])->saveQuietly();

        return true;
    }

    public function releaseLock(?User $user = null): void
    {
        if ($user && $this->locked_by && (int) $this->locked_by !== (int) $user->id) {
            return; // never release someone else's live lock
        }

        $this->forceFill(['locked_by' => null, 'locked_at' => null, 'lock_heartbeat_at' => null])->saveQuietly();
    }

    public function hasSalesperson(): bool
    {
        return $this->salesperson_id !== null;
    }

    /** Shared order number of every record created from this enquiry (falls back to the enquiry ref). */
    public function orderNumber(): string
    {
        return $this->order_number ?: ($this->reference_no ?? 'ENQ-'.$this->id);
    }

    /** Human label for where the order came from. */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_SALESPERSON_LINK => 'Salesperson link',
            self::SOURCE_WALK_IN => 'Walk-in',
            self::SOURCE_ADMIN => 'Admin entry',
            default => 'Customer portal',
        };
    }
}
