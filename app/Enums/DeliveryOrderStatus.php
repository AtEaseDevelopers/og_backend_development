<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryOrderStatus: string implements HasColor, HasLabel
{
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Transferred = 'transferred';
    case Reassigned = 'reassigned';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Transferred => 'Transferred',
            self::Reassigned => 'Reassigned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Assigned => 'gray',
            self::InTransit => 'warning',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Transferred => 'info',
            self::Reassigned => 'primary',
            self::Cancelled => 'gray',
        };
    }
}
