<?php

namespace App\Console\Commands;

use App\Domains\Delivery\Actions\FlagMissingCsns;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FlagMissingCsnsCommand extends Command
{
    protected $signature = 'og:flag-missing-csns {--date=}';

    protected $description = 'Mark delivered CSNs as missing after the configured grace period';

    public function handle(FlagMissingCsns $action): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $logs = $action->execute($date);
        $this->info('Flagged '.$logs->count().' missing CSN(s).');

        return self::SUCCESS;
    }
}
