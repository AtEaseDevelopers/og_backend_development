<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Credit = 'credit';
    case CodPending = 'cod_pending';
    case CodCollected = 'cod_collected';
    case CodReconciled = 'cod_reconciled';
}
