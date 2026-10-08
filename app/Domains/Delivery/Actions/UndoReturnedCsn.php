<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admin correction on the CSN page: a CSN scanned as returned by mistake goes back to "not returned"
 * (pending return once delivered). The return record is removed and the change is logged.
 */
class UndoReturnedCsn
{
    public function execute(ConsignmentNote $csn, User $actor): ConsignmentNote
    {
        if ($csn->return_status !== 'returned' && ! $csn->returnedCsn()->exists()) {
            throw new InvalidArgumentException($csn->number.' is not marked as returned.');
        }

        return DB::transaction(function () use ($csn, $actor) {
            $csn->returnedCsn()->delete();
            $csn->update(['return_status' => $csn->status === CsnStatus::Delivered ? 'pending_return' : 'not_required']);

            activity()->performedOn($csn)->causedBy($actor)->log('CSN marked as not returned (return scan undone)');

            return $csn->fresh();
        });
    }
}
