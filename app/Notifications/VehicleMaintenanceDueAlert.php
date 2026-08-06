<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class VehicleMaintenanceDueAlert extends Notification
{
    use Queueable;

    public function __construct(public Collection $records) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Vehicle maintenance due',
            'message' => $this->records->count().' maintenance/permit item(s) due soon',
            'record_ids' => $this->records->pluck('id')->all(),
        ];
    }
}
