<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentSubmissionStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Verified = 'verified';   // approval level 1 (evidence verified) — cash / counter only
    case Approved = 'approved';   // released: payment recorded
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Verified => 'Verified (L1)',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Verified => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Verified], true);
    }
}
