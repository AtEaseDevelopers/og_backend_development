<?php

namespace App\Console\Commands;

use App\Domains\MasterData\Actions\FlagVehicleMaintenanceDue;
use Illuminate\Console\Command;

class FlagVehicleMaintenanceDueCommand extends Command
{
    protected $signature = 'og:flag-vehicle-maintenance-due {--notify=1}';

    protected $description = 'Flag vehicle maintenance/permit items due within the alert window';

    public function handle(FlagVehicleMaintenanceDue $action): int
    {
        $due = $action->execute(notify: (bool) $this->option('notify'));
        $this->info('Due soon: '.$due->count());

        return self::SUCCESS;
    }
}
