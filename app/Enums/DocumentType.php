<?php

namespace App\Enums;

enum DocumentType: string
{
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
}
