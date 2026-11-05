<?php

namespace App\Domains\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Section P: one change a portal user made to their company profile. Major changes (company name, BRN, TIN,
 * e-invoice buyer details) wait for Admin re-verification; the rest apply at once. Every row is the audit trail.
 */
class CustomerProfileChange extends Model
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const SECTIONS = [
        'company' => 'Company details',
        'einvoice' => 'E-invoice details',
        'pic' => 'Person in charge',
        'address' => 'Address',
        'notifications' => 'Email notifications',
    ];

    /** Customer fields that only change after Admin re-verification. */
    public const MAJOR_FIELDS = [
        'company_name', 'brn', 'tin', 'sst_registration_no',
        'einvoice_buyer_name', 'einvoice_tin', 'einvoice_id_type', 'einvoice_id_value', 'einvoice_address',
    ];

    public const FIELD_LABELS = [
        'company_name' => 'Company name',
        'brn' => 'BRN',
        'tin' => 'TIN',
        'sst_registration_no' => 'SST registration no.',
        'address' => 'Billing address',
        'phone' => 'Contact number',
        'email' => 'Company email',
        'einvoice_buyer_name' => 'E-invoice buyer name',
        'einvoice_tin' => 'E-invoice TIN',
        'einvoice_id_type' => 'E-invoice ID type',
        'einvoice_id_value' => 'E-invoice ID number',
        'einvoice_address' => 'E-invoice address',
        'email_notifications' => 'Email notifications',
        'name' => 'Name',
        'is_default' => 'Default',
        'type' => 'Type',
        'label' => 'Label',
        'postcode' => 'Postcode',
        'city' => 'City',
        'state' => 'State',
        'google_maps_url' => 'Google Maps link',
    ];

    protected $fillable = [
        'customer_id', 'user_id', 'section', 'action', 'subject_id', 'changes',
        'status', 'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
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

    public function sectionLabel(): string
    {
        return self::SECTIONS[$this->section] ?? ucfirst($this->section);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'Applied',
            self::STATUS_PENDING => 'Pending verification',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst((string) $this->status),
        };
    }

    /** "Company name: Old → New; BRN: … → …" */
    public function summary(): string
    {
        return collect($this->changes ?? [])
            ->map(function ($pair, string $field) {
                [$old, $new] = array_pad((array) $pair, 2, null);
                $label = self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));

                return $label.': '.self::display($old).' → '.self::display($new);
            })
            ->implode('; ');
    }

    private static function display(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return filled($value) ? (string) $value : '—';
    }
}
