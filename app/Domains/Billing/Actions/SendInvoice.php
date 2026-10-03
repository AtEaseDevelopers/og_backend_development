<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Models\User;
use App\Support\InvoiceDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;

/**
 * Section G: once an Invoice / Cash Bill is confirmed, Admin can send it to the customer
 * by email (PDF attached). Delivery is recorded in the notification history.
 */
class SendInvoice
{
    public function __construct(private SendNotification $notify) {}

    public function execute(Invoice $invoice, User $actor, ?string $toEmail = null, ?string $note = null): NotificationLog
    {
        $invoice->loadMissing(['customer', 'quotation', 'sourceBranch']);

        if ($invoice->status === 'draft' || $invoice->status === 'cancelled') {
            throw new InvalidArgumentException('Only confirmed invoices can be sent (status: '.$invoice->status.').');
        }

        $email = $toEmail ?: $invoice->customer?->email;

        if (blank($email)) {
            throw new InvalidArgumentException('The customer has no email address. Enter one to send the invoice.');
        }

        $document = app(InvoiceDocumentData::class)->fromInvoice($invoice);
        $pdf = Pdf::loadView('pdf.invoice', ['document' => $document, 'meta' => $document['meta']])->setPaper('a4')->output();

        $label = $invoice->isCashBill() ? 'Cash Bill' : 'Invoice';

        $log = $this->notify->execute(
            event: 'invoice_sent',
            recipient: ['type' => 'customer', 'name' => $invoice->customer?->company_name, 'email' => $email, 'phone' => $invoice->customer?->phone],
            subject: $label.' '.$invoice->number.' from '.($invoice->sourceBranch?->company_name ?? config('app.name')),
            message: sprintf(
                "Dear %s,\n\nPlease find attached %s %s dated %s for RM %s%s.\n%s\nThank you for your business.",
                $invoice->customer?->company_name ?? 'Customer',
                $label,
                $invoice->number,
                $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
                number_format((float) $invoice->total_amount, 2),
                $invoice->quotation ? ' (order '.$invoice->quotation->number.')' : '',
                $note ? "\n".$note."\n" : '',
            ),
            related: $invoice,
            channels: [NotificationLog::CHANNEL_EMAIL],
            attachment: ['data' => $pdf, 'name' => $invoice->number.'.pdf', 'mime' => 'application/pdf'],
        )->first();

        if ($log->status === NotificationLog::STATUS_SENT) {
            $invoice->update(['sent_at' => now()]);
        }

        activity()->performedOn($invoice)->causedBy($actor)->withProperties(['to' => $email, 'status' => $log->status])->log($label.' emailed to customer');

        return $log;
    }
}
