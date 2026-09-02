<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalEnquiry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'customer_id', 'company_id', 'branch_id', 'user_id', 'reference_no', 'pickup_address',
        'pickup_maps_url', 'preferred_delivery_date', 'special_requirements',
        'status', 'quotation_id', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'preferred_delivery_date' => 'date',
            'payload' => 'array',
            'status' => PortalEnquiryStatus::class,
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
