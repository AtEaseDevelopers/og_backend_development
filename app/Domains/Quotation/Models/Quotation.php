<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quotation extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'number', 'company_id', 'branch_id', 'customer_id', 'salesperson_id', 'portal_enquiry_id',
        'status', 'valid_until', 'pricing_source', 'subtotal', 'tax_amount',
        'total_amount', 'notes', 'rejection_reason', 'sent_at', 'confirmed_at',
        'converted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(QuotationDestination::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(QuotationStatusLog::class);
    }

    public function consignmentNotes(): HasMany
    {
        return $this->hasMany(ConsignmentNote::class);
    }

    public function portalEnquiry(): BelongsTo
    {
        return $this->belongsTo(PortalEnquiry::class);
    }
}
