<?php

namespace App\Enums;

enum QuotationStatus: string
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

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
