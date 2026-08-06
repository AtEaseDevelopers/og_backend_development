<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IncompleteDeliveriesAlert extends Notification
{
    use Queueable;

    public function __construct(
        public Carbon $date,
        public Collection $alerts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Incomplete deliveries after 4pm',
            'message' => sprintf(
                '%d delivery task(s) still incomplete for %s',
                $this->alerts->count(),
                $this->date->toDateString()
            ),
            'alert_date' => $this->date->toDateString(),
            'count' => $this->alerts->count(),
            'delivery_order_ids' => $this->alerts->pluck('delivery_order.id')->all(),
        ];
    }
}
