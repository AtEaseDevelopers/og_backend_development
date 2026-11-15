<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\OtherInvoice;
use App\Support\AmountInWords;
use App\Support\CurrentCompany;
use App\Support\InvoiceDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/** The Other Invoice as a PDF: the invoice's letterhead and billing block, the lines as Item / Description / Amount. */
class OtherInvoicePdfController
{
    public function __invoke(string $tenant, int|string $otherInvoice): Response
    {
        abort_unless(Filament::auth()->user(), 403);

        $record = OtherInvoice::query()->with(['lines', 'customer', 'company.branch', 'branch'])->findOrFail($otherInvoice);
        $currentTenant = Filament::getTenant() ?? CurrentCompany::get();

        if ($currentTenant && $record->company_id && (int) $record->company_id !== (int) $currentTenant->getKey()) {
            abort(404);
        }

        $document = static::document($record);
        $pdf = Pdf::loadView('pdf.invoice', ['document' => $document, 'meta' => $document['meta']])->setPaper('a4');
        \App\Support\PdfPageNumbers::stamp($pdf);

        return $pdf->stream($record->number.'.pdf');
    }

    /** @return array<string, mixed>  the invoice document's shape (documents.invoice), with `other` set */
    public static function document(OtherInvoice $record): array
    {
        $company = $record->company;
        $branch = $record->branch ?: $company?->branch;
        $customer = $record->customer;
        $pic = $customer?->pics()->orderByDesc('is_default')->orderBy('id')->first(['name', 'phone']);
        $rate = $record->charges_sst && (float) $record->subtotal > 0 ? (int) round((float) $record->tax_amount / (float) $record->subtotal * 100) : null;

        return [
            'other' => true,
            'letterhead' => [
                'logo_src' => null,
                'company_name' => $company?->name ?: $branch?->company_name ?: 'O&G Transport',
                'reg_no' => $company?->brn ?: $branch?->company_no,
                'address' => $company?->address ?: $branch?->address,
                'phone' => $company?->phone ?: $branch?->phone,
                'email' => $company?->email ?: $branch?->email,
                'sst_no' => ($company?->tin ?: $branch?->sst_no) ?? null,
            ],
            'meta' => [
                'number' => $record->number,
                'title' => 'OTHER INVOICE',
                'invoice_date' => $record->invoice_date?->format('d/m/Y'),
                'account_no' => $customer?->code ?: '—',
            ],
            'bill_to' => [
                'name' => $customer?->company_name ?: '—',
                'reg_no' => $customer?->brn,
                'address' => $customer?->address ?: '—',
                'attention' => $customer?->attention ?: $pic?->name,
                'phone' => $customer?->phone ?: $pic?->phone,
                'email' => $customer?->email,
            ],
            'lines' => $record->lines->map(fn ($line) => [
                'item' => (string) $line->item_name,
                'description' => (string) $line->description,
                'amount' => number_format((float) $line->amount, 2),
            ])->all(),
            'totals' => ['subtotal_label' => 'Sub Total (Excluding Tax)'] + app(InvoiceDocumentData::class)->totals((float) $record->total_amount, (float) $record->tax_amount, (float) $record->subtotal, $rate),
            'amount_in_words' => AmountInWords::ringgit((float) $record->total_amount),
            'remarks' => $record->remarks,
            'bank_accounts' => config('og.invoice.bank_accounts', []),
            'einvoice_qr' => null,
        ];
    }
}
