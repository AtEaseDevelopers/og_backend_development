<?php

namespace App\Enums;

enum CsnStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
