<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Invoice / Cash Bill generation state of an order (section G). */
enum BillingStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingRelease = 'awaiting_release';
    case Generated = 'generated';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::AwaitingPayment => 'Awaiting payment',
            self::AwaitingRelease => 'Awaiting admin release',
            self::Generated => 'Billing issued',
            self::Failed => 'Billing generation failed',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::AwaitingPayment => 'warning',
            self::AwaitingRelease => 'warning',
            self::Generated => 'success',
            self::Failed => 'danger',
        };
    }
}
