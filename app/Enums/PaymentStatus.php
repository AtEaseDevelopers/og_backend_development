<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Credit = 'credit';
    case CodPending = 'cod_pending';
    case CodCollected = 'cod_collected';
    case CodReconciled = 'cod_reconciled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CodPending => 'COD Pending',
            self::CodCollected => 'COD Collected',
            self::CodReconciled => 'COD Reconciled',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Paid, self::CodCollected, self::CodReconciled => 'success',
            self::Partial => 'warning',
            self::Credit => 'info',
            self::Unpaid, self::CodPending => 'gray',
        };
    }
}
