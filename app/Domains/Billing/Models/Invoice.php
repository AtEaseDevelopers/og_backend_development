<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'number', 'company_id', 'source_branch_id', 'customer_id', 'consignment_note_id', 'type',
        'billing_month', 'status', 'subtotal', 'tax_amount', 'rounding_amount',
        'total_amount', 'invoice_date', 'due_date', 'autocount_sync_status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function einvoiceSubmission(): HasOne
    {
        return $this->hasOne(EinvoiceSubmission::class);
    }
}
