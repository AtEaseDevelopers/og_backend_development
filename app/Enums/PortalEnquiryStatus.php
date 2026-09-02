<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PortalEnquiryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Quoted = 'quoted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::InReview => 'In Review',
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
            self::Pending => 'warning',
            self::InReview => 'info',
            self::Quoted => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
