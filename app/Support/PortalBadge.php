<?php

namespace App\Support;

/** A coloured status pill for the customer portal pages. */
class PortalBadge
{
    /** @param  array{label: string, tone: string}  $status  tone: green|amber|red|blue|stone */
    public static function html(array $status): string
    {
        [$bg, $fg] = match ($status['tone']) {
            'green' => ['#dcfce7', '#166534'],
            'amber' => ['#fef3c7', '#92400e'],
            'red' => ['#fee2e2', '#991b1b'],
            'blue' => ['#dbeafe', '#1e40af'],
            default => ['#f5f5f4', '#57534e'],
        };

        return '<span class="badge" style="background:'.$bg.';color:'.$fg.'">'.e($status['label']).'</span>';
    }
}
