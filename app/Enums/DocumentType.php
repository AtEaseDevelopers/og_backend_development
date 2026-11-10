<?php

namespace App\Enums;

enum DocumentType: string
{
    case Order = 'ORD';
    case Quotation = 'QT';
    case Csn = 'CSN';
    case Do = 'DO';
    case JobSheet = 'JS';
    case Invoice = 'INV';
    case Receipt = 'OR';
    case Proforma = 'PI';
    case BreakBulk = 'BB';
    case Subsheet = 'SS';
    case CommissionBatch = 'CB';
    case CommissionSlip = 'CS';
    case CommissionPo = 'CPO';
    case CommissionPi = 'CPI';
    case CashBill = 'CBL';
    // a COD invoice has its own running number (user, 11 Nov 2026)
    case CodInvoice = 'CIV';
    case RefundNote = 'RN';
    case PaymentVoucher = 'PV';
    /** the Quotations page (Q-KL-261000001) */
    case Quote = 'Q';
}
