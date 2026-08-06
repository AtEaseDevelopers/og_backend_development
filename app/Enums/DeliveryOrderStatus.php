<?php

namespace App\Enums;

enum DeliveryOrderStatus: string
{
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Transferred = 'transferred';
    case Reassigned = 'reassigned';
    case Cancelled = 'cancelled';
}
