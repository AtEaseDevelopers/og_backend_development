<?php

namespace App\Enums;

enum CsnBillingType: string
{
    case CashBill = 'cash_bill';
    case Cod = 'cod';
    case Term = 'term';

    public function label(): string
    {
        return match ($this) {
            self::CashBill => 'Cash Bill',
            self::Cod => 'Advance Taken / COD',
            self::Term => 'Term Billing',
        };
    }
}
