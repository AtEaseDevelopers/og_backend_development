<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Operational sequence: pending_assignment → assigned → in_transit → delivered.
 * (draft / confirmed remain for manually created legacy CSNs.)
 */
enum CsnStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PendingAssignment = 'pending_assignment';
    case Assigned = 'assigned';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingAssignment => 'Pending Lorry Assignment',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Confirmed, self::Delivered => 'success',
            self::Assigned => 'info',
            self::InTransit, self::PendingAssignment => 'warning',
            self::Draft, self::Cancelled => 'gray',
        };
    }

    /** Statuses in which the CSN can still be claimed / assigned to a lorry. */
    public function isAwaitingAssignment(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed, self::PendingAssignment], true);
    }
}
