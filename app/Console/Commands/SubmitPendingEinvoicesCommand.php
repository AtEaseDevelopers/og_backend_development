<?php

namespace App\Console\Commands;

use App\Domains\Integration\Actions\SubmitEinvoice;
use App\Domains\Integration\Models\EinvoiceSubmission;
use Illuminate\Console\Command;

class SubmitPendingEinvoicesCommand extends Command
{
    protected $signature = 'og:submit-pending-einvoices';

    protected $description = 'Auto-submit MyInvois submissions that are ready';

    public function handle(SubmitEinvoice $action): int
    {
        if (! config('og.myinvois.auto_submit')) {
            $this->warn('OG_MYINVOIS_AUTO_SUBMIT is disabled.');

            return self::SUCCESS;
        }

        $pending = EinvoiceSubmission::query()
            ->with('invoice.customer')
            ->whereIn('status', ['ready', 'failed', 'pending_buyer'])
            ->whereNotNull('buyer_info')
            ->limit(50)
            ->get();

        $ok = 0;
        foreach ($pending as $submission) {
            try {
                $result = $action->execute($submission->invoice, null, 'scheduled');
                if ($result->status === 'valid') {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $this->error('#'.$submission->id.' '.$e->getMessage());
            }
        }

        $this->info("Submitted OK: {$ok} / ".$pending->count());

        return self::SUCCESS;
    }
}
