<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Billing\Models\Receipt;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/** Billing documents as PDF, shared by Admin and the customer portal. */
class DocumentPdf
{
    public static function invoice(Invoice $invoice): DomPdf
    {
        $document = app(InvoiceDocumentData::class)->fromInvoice($invoice);

        return Pdf::loadView('pdf.invoice', [
            'document' => $document,
            'meta' => $document['meta'],
        ])->setPaper('a4');
    }

    public static function proforma(ProformaInvoice $proforma): DomPdf
    {
        $proforma->loadMissing(['quotation.lines', 'quotation.destinations', 'quotation.portalEnquiry', 'quotation.salesperson', 'quotation.fromLocation', 'quotation.toLocation', 'customer', 'company']);

        $quotation = $proforma->quotation;
        $branch = $quotation?->branch ?? CurrentCompany::branch();
        $destinations = $quotation?->destinations->keyBy('id') ?? collect();

        $lines = ($quotation?->lines ?? collect())->map(fn ($line) => [
            'item' => $line->item_name,
            'route' => $destinations->get($line->quotation_destination_id)?->consignee_name,
            'qty' => QuantityLabel::format($line->quantity, $line->uom),
            'unit' => number_format((float) $line->unit_price, 2),
            'total' => number_format((float) $line->line_total, 2),
        ])->values()->all();

        return Pdf::loadView('pdf.proforma-invoice', [
            'proforma' => $proforma,
            'quotation' => $quotation,
            'branch' => $branch,
            'lines' => $lines,
            'outstanding' => max(0, (float) $proforma->total_amount - (float) $proforma->paid_amount),
        ])->setPaper('a4');
    }

    public static function receipt(Receipt $receipt): DomPdf
    {
        $receipt->loadMissing(['payment.invoice', 'payment.quotation', 'customer']);

        return Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'payment' => $receipt->payment,
            'customer' => $receipt->customer,
            'branch' => Branch::query()->find($receipt->source_branch_id),
        ])->setPaper('a4');
    }

    /** @param  array<string, mixed>  $statement  from StatementData::build() */
    public static function statement(Customer $customer, array $statement): DomPdf
    {
        return Pdf::loadView('pdf.statement', [
            'customer' => $customer,
            'statement' => $statement,
            'branch' => Branch::query()->find($customer->branch_id),
        ])->setPaper('a4');
    }
}
