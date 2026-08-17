<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CsnStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Confirmed, self::Delivered => 'success',
            self::Assigned => 'info',
            self::InTransit => 'warning',
            self::Draft, self::Cancelled => 'gray',
        };
    }
}
