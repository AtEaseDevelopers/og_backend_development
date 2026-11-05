<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Billing\Models\Receipt;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use App\Support\DocumentPdf;
use App\Support\StatementData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Section P: the customer's financial documents (invoices, receipts, proformas, e-invoices, statements). */
class DocumentController extends Controller
{
    use UsesPortalCustomer;

    public const TABS = [
        'invoices' => 'Invoices',
        'receipts' => 'Official receipts',
        'proformas' => 'Proforma invoices',
        'einvoices' => 'E-invoices',
    ];

    public function index(Request $request): View
    {
        $customer = $this->portalCustomer();
        $filters = $request->validate([
            'tab' => ['nullable', 'in:'.implode(',', array_keys(self::TABS))],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $tab = $filters['tab'] ?? 'invoices';
        $search = $filters['search'] ?? null;

        $dated = fn ($query, string $column) => $query
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate($column, '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate($column, '<=', $d));

        $records = match ($tab) {
            'receipts' => $dated(Receipt::query()->with('payment.invoice:id,number', 'payment.quotation:id,number'), 'created_at')
                ->where('customer_id', $customer->id)
                ->when($search, fn ($q, $s) => $q->where('number', 'like', "%{$s}%"))
                ->latest(),
            'proformas' => $dated(ProformaInvoice::query()->with('quotation:id,number'), 'created_at')
                ->where('customer_id', $customer->id)
                ->when($search, fn ($q, $s) => $q->where('number', 'like', "%{$s}%"))
                ->latest(),
            'einvoices' => $dated(Invoice::query()->with('einvoiceSubmission'), 'invoice_date')
                ->where('customer_id', $customer->id)
                ->whereHas('einvoiceSubmission')
                ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")
                    ->orWhereHas('einvoiceSubmission', fn ($e) => $e->where('uuid', 'like', "%{$s}%"))))
                ->latest('invoice_date'),
            default => $dated(Invoice::query()->with(['payments', 'einvoiceSubmission', 'quotation:id,number']), 'invoice_date')
                ->where('customer_id', $customer->id)
                ->whereNot('status', InvoiceStatus::Draft->value)
                ->when($search, fn ($q, $s) => $q->where('number', 'like', "%{$s}%"))
                ->latest('invoice_date'),
        };

        return view('portal.documents', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'filters' => $filters,
            'records' => $records->paginate(20)->withQueryString(),
        ]);
    }

    public function invoice(Invoice $invoice): Response
    {
        $this->authorizeCustomerRecord($invoice->customer_id);
        abort_if($invoice->status === InvoiceStatus::Draft->value, 404);

        return DocumentPdf::invoice($invoice)->download(($invoice->number ?: 'invoice').'.pdf');
    }

    /** The validated e-invoice from LHDN when stored, else the invoice carrying its UUID. */
    public function einvoice(Invoice $invoice): Response
    {
        $this->authorizeCustomerRecord($invoice->customer_id);
        $submission = $invoice->einvoiceSubmission;
        abort_unless($submission, 404);

        foreach (['local', 'public'] as $disk) {
            if ($submission->validated_pdf_path && Storage::disk($disk)->exists($submission->validated_pdf_path)) {
                return Storage::disk($disk)->download($submission->validated_pdf_path, 'e-invoice-'.$invoice->number.'.pdf');
            }
        }

        return DocumentPdf::invoice($invoice)->download('e-invoice-'.$invoice->number.'.pdf');
    }

    public function receipt(Receipt $receipt): Response
    {
        $this->authorizeCustomerRecord($receipt->customer_id);

        return DocumentPdf::receipt($receipt)->download(($receipt->number ?: 'receipt').'.pdf');
    }

    public function proforma(ProformaInvoice $proforma): Response
    {
        $this->authorizeCustomerRecord($proforma->customer_id);

        return DocumentPdf::proforma($proforma)->download(($proforma->number ?: 'proforma').'.pdf');
    }

    public function statement(Request $request, StatementData $data): View
    {
        $customer = $this->portalCustomer();
        [$from, $to] = $this->period($request);

        return view('portal.statement', [
            'customer' => $customer,
            'statement' => $data->build($customer, $from, $to),
            'month' => $request->query('month') ?: ($request->filled('from') || $request->filled('to') ? null : now()->format('Y-m')),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function statementPdf(Request $request, StatementData $data): Response
    {
        $customer = $this->portalCustomer();
        [$from, $to] = $this->period($request);
        $statement = $data->build($customer, $from, $to);

        return DocumentPdf::statement($customer, $statement)
            ->download('statement-'.$customer->code.'-'.$statement['to']->format('Y-m').'.pdf');
    }

    /** A month (Y-m) or a from / to date range; defaults to the current month. @return array{0: string, 1: string} */
    private function period(Request $request): array
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
            $to = $request->query('to') ?: now()->toDateString();

            return [$from, $to];
        }

        $month = Carbon::createFromFormat('Y-m', $request->query('month') ?: now()->format('Y-m'))->startOfMonth();

        return [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
    }
}
