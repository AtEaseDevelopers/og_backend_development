<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideCreditApproval
{
    public function execute(CreditApprovalRequest $request, User $actor, bool $approve, ?string $remarks = null): CreditApprovalRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException('Approval request is not pending.');
        }

        if (! $actor->hasAnyRole(['hq_admin', 'branch_manager']) && ! $actor->is_hq) {
            throw new InvalidArgumentException('Only branch managers or HQ can decide credit approvals.');
        }

        return DB::transaction(function () use ($request, $actor, $approve, $remarks) {
            $request->update([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by' => $actor->id,
                'remarks' => $remarks,
                'decided_at' => now(),
            ]);

            $quotation = $request->quotation;
            if ($quotation) {
                $from = $quotation->status->value;
                $to = $approve ? QuotationStatus::Confirmed : QuotationStatus::Rejected;

                $quotation->update([
                    'status' => $to,
                    'confirmed_at' => $approve ? now() : null,
                    'rejection_reason' => $approve ? null : ($remarks ?? 'Credit approval rejected'),
                ]);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'from_status' => $from,
                    'to_status' => $to->value,
                    'user_id' => $actor->id,
                    'remarks' => ($approve ? 'Credit approved' : 'Credit rejected').($remarks ? ": {$remarks}" : ''),
                ]);
            }

            return $request->fresh(['customer', 'quotation', 'approver']);
        });
    }
}
