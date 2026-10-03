<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stage of the main order record (Enquiry → Quotation → Confirmation → Proforma stay on one row).
 *
 * draft            pricing in progress (admin / salesperson editing)
 * sent             quotation issued to the customer, awaiting review
 * pending_review   no customer response yet; listed under "Pending Customer Review"
 * negotiation      customer asked for a price / scope change → revise into a new version
 * accepted         customer confirmed this version (proforma generated)
 * pending_approval credit approval required before the order can proceed
 * confirmed        order confirmed (credit ok / non-credit) → payment / admin release stage
 * converted        billing issued and CSN(s) created
 * rejected         customer or admin rejected
 * superseded       replaced by a newer version
 * closed           auto-closed after the pending-review window with no update
 * expired / cancelled
 */
enum QuotationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PendingReview = 'pending_review';
    case Negotiation = 'negotiation';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case Converted = 'converted';
    case Superseded = 'superseded';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::PendingReview => 'Pending Customer Review',
            self::Negotiation => 'Negotiation Required',
            self::Sent => 'Quotation Issued',
            self::Accepted => 'Customer Confirmed',
            self::Closed => 'Closed Case',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Confirmed, self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Sent => 'info',
            self::Converted => 'purple',
            self::PendingApproval, self::PendingReview, self::Negotiation => 'warning',
            self::Draft, self::Expired, self::Cancelled, self::Superseded, self::Closed => 'gray',
        };
    }

    /** Statuses in which the customer may still accept / reject this version. */
    public function isCustomerActionable(): bool
    {
        return in_array($this, [self::Sent, self::PendingReview, self::Negotiation], true);
    }

    /** Statuses that count as an order already confirmed by the customer. */
    public function isConfirmedOrLater(): bool
    {
        return in_array($this, [self::Accepted, self::PendingApproval, self::Confirmed, self::Converted], true);
    }

    /** Statuses that keep a version editable by admin / sales. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Negotiation], true);
    }

    /** Terminal states for a version. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Converted, self::Rejected, self::Expired, self::Superseded, self::Closed, self::Cancelled], true);
    }
}
