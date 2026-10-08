<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\SendInvoice;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Http\Controllers\Admin\ConsignmentNotePdfController;
use App\Http\Controllers\Admin\DeliveryOrderPdfController;
use App\Http\Controllers\Admin\ProformaInvoicePdfController;
use App\Http\Controllers\Admin\QuotationPdfController;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Email one chosen document of an order to the customer (PDF attached): any version of the quotation, the
 * proforma invoice, an invoice / cash bill, a CSN or a DO. The order's activity records which document went
 * to which address.
 */
class SendOrderDocument
{
    public function __construct(private SendNotification $notify) {}

    /**
     * Documents of the order that can be emailed, newest / most relevant first.
     *
     * @return array<string, string> key ("type:id") => label
     */
    public static function options(Quotation $order): array
    {
        $order->loadMissing(['invoices', 'proformaInvoice', 'consignmentNotes.deliveryOrder']);
        $money = fn ($v) => 'RM '.number_format((float) $v, 2);
        $options = [];

        foreach ($order->invoices->sortByDesc('id') as $invoice) {
            if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
                continue;
            }
            $options['invoice:'.$invoice->id] = ($invoice->isCashBill() ? 'Cash Bill ' : 'Invoice ').$invoice->number.' · '.$money($invoice->total_amount)
                .($invoice->sent_at ? ' · last sent '.$invoice->sent_at->format('d/m/Y H:i') : '');
        }

        if ($order->proformaInvoice) {
            $options['proforma:'.$order->proformaInvoice->id] = 'Proforma invoice '.$order->proformaInvoice->number.' · '.$money($order->proformaInvoice->total_amount);
        }

        foreach (self::versions($order) as $version) {
            $options['quotation:'.$version->id] = 'Quotation '.$version->number.' · version '.$version->version
                .((int) $version->id === (int) $order->id ? ' (current)' : ' (old version)').' · '.$money($version->total_amount);
        }

        foreach ($order->consignmentNotes as $csn) {
            $options['csn:'.$csn->id] = 'CSN '.$csn->number;

            if ($csn->deliveryOrder) {
                $options['do:'.$csn->deliveryOrder->id] = 'DO '.$csn->deliveryOrder->number.' · CSN '.$csn->number;
            }
        }

        return $options;
    }

    /** The PDF of a document key ("type:id"), the same file the PDF links open; null when unknown. */
    public static function pdfUrl(string $key): ?string
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
        [$route, $param] = match ($type) {
            'invoice' => ['invoices', 'invoice'],
            'proforma' => ['proforma-invoices', 'proformaInvoice'],
            'quotation' => ['quotations', 'quotation'],
            'csn' => ['consignment-notes', 'consignmentNote'],
            'do' => ['delivery-orders', 'deliveryOrder'],
            default => [null, null],
        };

        if (! $route || ! $id) {
            return null;
        }

        try {
            return route('filament.admin.'.$route.'.pdf', ['tenant' => Filament::getTenant(), $param => (int) $id]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The suggested subject and body for a document (editable in the email window, like composing in Gmail).
     *
     * @return array{subject: string, body: string}
     */
    public static function defaultEmail(Quotation $order, string $key): array
    {
        $label = self::options($order)[$key] ?? 'Document';
        $title = explode(' · ', $label)[0];
        $order->loadMissing(['customer', 'branch']);
        $sender = $order->branch?->company_name ?? config('app.name');

        return [
            'subject' => $title.' from '.$sender,
            'body' => sprintf(
                // the email layout adds the sign-off
                "Dear %s,\n\nPlease find attached %s for order %s.\n\nThank you for your business.",
                $order->customer?->company_name ?? 'Customer',
                $title,
                $order->orderNumber(),
            ),
        ];
    }

    public function execute(Quotation $order, string $key, User $actor, ?string $toEmail = null, ?string $subject = null, ?string $body = null): NotificationLog
    {
        $label = self::options($order)[$key] ?? null;

        if ($label === null) {
            throw new InvalidArgumentException('Choose a document of this order to send.');
        }

        [$type, $id] = explode(':', $key, 2);
        $email = $toEmail ?: $order->customer?->email;

        if (blank($email)) {
            throw new InvalidArgumentException('The customer has no email address. Enter one to send the document.');
        }

        if ($type === 'invoice') {
            $log = app(SendInvoice::class)->execute(Invoice::query()->findOrFail((int) $id), $actor, $email, null, $subject, $body);
        } else {
            [$number, $controller] = match ($type) {
                'proforma' => [ProformaInvoice::query()->findOrFail((int) $id)->number, ProformaInvoicePdfController::class],
                'quotation' => [Quotation::query()->findOrFail((int) $id)->number, QuotationPdfController::class],
                'csn' => [ConsignmentNote::query()->findOrFail((int) $id)->number, ConsignmentNotePdfController::class],
                'do' => [DeliveryOrder::query()->findOrFail((int) $id)->number, DeliveryOrderPdfController::class],
            };
            $default = self::defaultEmail($order, $key);
            // the same PDF the "PDF" links open
            $pdf = app($controller)((string) (Filament::getTenant()?->getRouteKey() ?? ''), (int) $id)->getContent();

            $log = $this->notify->execute(
                event: 'document_sent',
                recipient: ['type' => 'customer', 'name' => $order->customer?->company_name, 'email' => $email, 'phone' => $order->customer?->phone],
                subject: filled($subject) ? trim((string) $subject) : $default['subject'],
                message: filled($body) ? (string) $body : $default['body'],
                related: $order,
                channels: [NotificationLog::CHANNEL_EMAIL],
                attachment: ['data' => $pdf, 'name' => $number.'.pdf', 'mime' => 'application/pdf'],
            )->first();
        }

        QuotationStatusLog::query()->create([
            'quotation_id' => $order->id,
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'user_id' => $actor->id,
            'remarks' => explode(' · ', $label)[0].' emailed to '.$email.' ('.$log->status.') · Subject: '.$log->subject,
            'meta' => ['document' => $key, 'to' => $email, 'subject' => $log->subject, 'notification_log_id' => $log->id],
        ]);

        return $log;
    }

    /** @return Collection<int, Quotation> every version of the order, newest first */
    private static function versions(Quotation $order): Collection
    {
        $rootId = $order->rootId();

        return Quotation::query()
            ->where(fn ($q) => $q->where('root_quotation_id', $rootId)->orWhere('id', $rootId))
            ->where('version', '<=', (int) $order->version)
            ->orderByDesc('version')
            ->get();
    }
}
