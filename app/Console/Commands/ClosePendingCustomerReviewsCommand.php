<?php

namespace App\Console\Commands;

use App\Domains\Quotation\Actions\ClosePendingCustomerReviews;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ClosePendingCustomerReviewsCommand extends Command
{
    protected $signature = 'og:close-pending-reviews {--date=}';

    protected $description = 'Move unanswered quotations to Pending Customer Review and close them after the configured number of days';

    public function handle(ClosePendingCustomerReviews $action): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $result = $action->execute($now);

        $this->info('Pending customer review: '.$result['pending']->count().' · Closed cases: '.$result['closed']->count());

        return self::SUCCESS;
    }
}
