<?php

namespace App\Enums;

enum JobSheetStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Completed = 'completed';
}
