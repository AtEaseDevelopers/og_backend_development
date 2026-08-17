<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuotationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Confirmed, self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Sent => 'info',
            self::Converted => 'purple',
            self::PendingApproval => 'warning',
            self::Draft, self::Expired, self::Cancelled => 'gray',
        };
    }
}
