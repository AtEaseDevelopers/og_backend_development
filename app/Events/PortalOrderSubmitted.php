<?php

namespace App\Events;

use App\Domains\Quotation\Models\PortalEnquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired the moment a customer submits a portal order; admin toasts listen for it via the live feed. */
class PortalOrderSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PortalEnquiry $enquiry) {}
}
