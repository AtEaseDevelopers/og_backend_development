<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateOrderBilling;
use App\Domains\Billing\Actions\GenerateProformaForQuotation;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Section E: the customer accepts the quotation.
 *  - same record becomes "Customer Confirmed" (accepted version, channel, date/time, evidence)
 *  - accepted version is locked from being overwritten
 *  - Proforma Invoice is generated and the customer is notified
 *  - credit customers pass through credit approval; others are confirmed straight away
 */
class AcceptQuotation
{
    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_CONSENT = 'consent_letter';

    public function __construct(
        private GenerateProformaForQuotation $proforma,
        private EvaluateCreditEligibility $credit,
        private SendNotification $notify,
    ) {}

    public function execute(
        Quotation $quotation,
        string $channel,
        ?string $confirmedByName = null,
        ?User $actor = null,
        ?string $consentEvidence = null,
    ): Quotation {
        $quotation->loadMissing(['customer', 'branch', 'salesperson', 'creator']);

        $status = $quotation->status;
        $adminOnBehalf = in_array($channel, [self::CHANNEL_ADMIN, self::CHANNEL_CONSENT, self::CHANNEL_WHATSAPP, self::CHANNEL_EMAIL], true);

        if (! ($status->isCustomerActionable() || ($adminOnBehalf && $status === QuotationStatus::Draft))) {
            throw new InvalidArgumentException('Quotation '.$quotation->number.' cannot be accepted in status "'.$status->getLabel().'".');
        }

        if (! $quotation->isLatestVersion()) {
            throw new InvalidArgumentException('Only the latest quotation version can be accepted.');
        }

        if ((float) $quotation->total_amount <= 0) {
            throw new InvalidArgumentException('Quotation has no pricing yet.');
        }

        $reviewer = $actor ?? $quotation->salesperson ?? $quotation->creator ?? User::query()->role('hq_admin')->first();

        $quotation = DB::transaction(function () use ($quotation, $channel, $confirmedByName, $actor, $consentEvidence, $reviewer) {
            $from = $quotation->status->value;

            $quotation->update([
                'status' => QuotationStatus::Accepted,
                'accepted_version' => $quotation->version,
                'confirmed_at' => now(),
                'confirmation_channel' => $channel,
                'confirmed_by_name' => $confirmedByName ?? $actor?->name ?? $quotation->customer?->company_name,
                'consent_evidence' => $consentEvidence,
                'pricing_reconfirmation_required' => $quotation->pricing_reconfirmation_required
                    ?? $quotation->customer?->pricing_reconfirmation_required,
                'order_type' => $quotation->order_type ?? $this->defaultOrderType($quotation),
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => QuotationStatus::Accepted->value,
                'user_id' => $actor?->id,
                'remarks' => 'Accepted via '.$channel.' (version '.$quotation->version.')'
                    .($confirmedByName ? ' by '.$confirmedByName : ''),
            ]);

            $this->proforma->execute($quotation);

            // Credit approval gate (section C / existing phase-2 rules)
            $needsApproval = false;

            if ($quotation->customer?->is_credit && $reviewer) {
                $result = $this->credit->execute($quotation->fresh(['customer', 'branch']), $reviewer, createRequest: true);
                $needsApproval = ! $result['allowed'];
            }

            $quotation->refresh();

            if (! $needsApproval) {
                $quotation->update([
                    'status' => QuotationStatus::Confirmed,
                    'billing_status' => $this->initialBillingStatus($quotation),
                ]);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'from_status' => QuotationStatus::Accepted->value,
                    'to_status' => QuotationStatus::Confirmed->value,
                    'user_id' => $actor?->id,
                    'remarks' => 'Order confirmed — awaiting payment / admin release',
                ]);
            } else {
                $quotation->update(['billing_status' => BillingStatus::AwaitingRelease]);
            }

            return $quotation->fresh(['customer', 'branch', 'proformaInvoice']);
        });

        $this->notifyCustomer($quotation);

        // COD orders proceed to billing automatically unless Admin blocks them (section F).
        if ($quotation->status === QuotationStatus::Confirmed && $quotation->orderType() === OrderType::Cod && ! $quotation->cod_blocked) {
            try {
                app(GenerateOrderBilling::class)->execute($quotation, $reviewer ?? $actor);
            } catch (Throwable) {
                // billing_status = failed is recorded by GenerateOrderBilling; Admin can retry
            }
        }

        return $quotation->fresh();
    }

    private function defaultOrderType(Quotation $quotation): OrderType
    {
        $customerDefault = OrderType::tryFrom((string) $quotation->customer?->default_order_type);

        return $customerDefault ?? ($quotation->customer?->is_credit ? OrderType::Term : OrderType::Cash);
    }

    private function initialBillingStatus(Quotation $quotation): BillingStatus
    {
        return match ($quotation->orderType()) {
            OrderType::Cash => BillingStatus::AwaitingPayment,
            OrderType::Cod => $quotation->cod_blocked ? BillingStatus::AwaitingRelease : BillingStatus::NotStarted,
            default => BillingStatus::AwaitingRelease,
        };
    }

    private function notifyCustomer(Quotation $quotation): void
    {
        $customer = $quotation->customer;
        $proforma = $quotation->proformaInvoice;

        $this->notify->execute(
            event: 'order_confirmed',
            recipient: ['type' => 'customer', 'name' => $customer?->company_name, 'email' => $customer?->email, 'phone' => $customer?->phone],
            subject: 'Your order has been confirmed — '.$quotation->number,
            message: sprintf(
                "Your order has been confirmed.\nQuotation %s (version %d) · Total RM %s\nProforma Invoice %s is ready.\n\n%s\n\nView your order: %s",
                $quotation->number,
                $quotation->accepted_version ?? $quotation->version,
                number_format((float) $quotation->total_amount, 2),
                $proforma?->number ?? '—',
                $proforma?->payment_instructions ?? '',
                route('portal.quotations.show', $quotation),
            ),
            related: $quotation,
        );
    }
}
