<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LinkPortalEnquiryQuotation
{
    public function execute(PortalEnquiry $enquiry, Quotation $quotation, User $actor): PortalEnquiry
    {
        // Section A: an enquiry may produce several orders. The enquiry keeps pointing at
        // its first order; every additional order is still linked back to the enquiry.
        return DB::transaction(function () use ($enquiry, $quotation, $actor) {
            $enquiry->update([
                'status' => PortalEnquiryStatus::Quoted->value,
                'quotation_id' => $enquiry->quotation_id ?: $quotation->id,
            ]);

            $quotation->update([
                'portal_enquiry_id' => $enquiry->id,
            ]);

            return $enquiry->fresh(['quotation', 'customer', 'branch', 'user']);
        });
    }
}
