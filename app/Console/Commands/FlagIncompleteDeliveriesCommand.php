<?php

namespace App\Console\Commands;

use App\Domains\Delivery\Actions\FlagIncompleteDeliveries;
use Illuminate\Console\Command;

class FlagIncompleteDeliveriesCommand extends Command
{
    protected $signature = 'og:flag-incomplete-deliveries {--date=} {--notify=1}';

    protected $description = 'Flag delivery tasks still incomplete after 4pm for the operating date';

    public function handle(FlagIncompleteDeliveries $action): int
    {
        $date = $this->option('date')
            ? \Illuminate\Support\Carbon::parse($this->option('date'))
            : now();
        $alerts = $action->execute($date, (bool) $this->option('notify'));

        $this->info(sprintf(
            'Flagged %d incomplete delivery task(s) for %s',
            $alerts->count(),
            $date->toDateString()
        ));

        return self::SUCCESS;
    }
}
