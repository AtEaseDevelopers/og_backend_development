<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Outstanding = 'outstanding';
}
