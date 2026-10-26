<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Commission\Models\CommissionSlip;
use App\Domains\MasterData\Models\Company;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a Purchase Order (PO) in the client's layout (user, 27 Oct 2026): the company name on top, the supplier
 * (the lorry company of the lorry's driver) on the left with P/O No, Date, Lorry Group, Supplier code, GST ID No. and
 * Page No. on the right, then a section per lorry — Job No. | Job Date | Description | Amount, Sub Total RM — and
 * the Total RM. A PO covers every slip of the batch with the same lorry company (one supplier, several lorries).
 */
class CommissionPoPdfController
{
    public function __invoke(string $tenant, int|string $slip): Response
    {
        abort_unless(Filament::auth()->user(), 403);

        $record = CommissionSlip::query()->with(['batch', 'lorry.defaultDriver', 'driver', 'sourceBranch'])->findOrFail($slip);
        $companyId = CurrentCompany::id();
        abort_if($companyId && (int) $record->company_id !== (int) $companyId, 404);

        $supplierCode = $this->supplierCode($record);
        // the slips of the batch for the same supplier (same lorry company), this one first
        $slips = CommissionSlip::query()
            ->with(['lorry.defaultDriver', 'driver', 'lines' => fn ($q) => $q->where('is_hidden', false)->with(['consignmentNote', 'deliveryOrder'])->orderBy('id')])
            ->where('commission_batch_id', $record->commission_batch_id)
            ->where('status', '!=', 'draft')
            ->get()
            ->filter(fn (CommissionSlip $s) => $s->id === $record->id || ($supplierCode !== '' && $this->supplierCode($s) === $supplierCode))
            ->sortBy(fn (CommissionSlip $s) => $s->id === $record->id ? 0 : 1)
            ->values();

        $sections = $slips->map(fn (CommissionSlip $s) => [
            'lorry' => (string) ($s->lorry?->registration_no ?? $s->driver?->lorry_number ?? '—'),
            'rows' => $s->lines->map(fn (CommissionLineItem $line) => [
                'job_no' => (string) ($line->consignmentNote?->number ?? $line->deliveryOrder?->number ?? '—'),
                'job_date' => $this->jobDate($line),
                'description' => (string) ($line->notes ?? ''),
                'amount' => (float) $line->amount,
            ])->values()->all(),
            'subtotal' => round((float) $s->lines->sum('amount'), 2),
        ])->values();

        $driver = $record->lorry?->defaultDriver ?? $record->driver;
        $company = ($record->company_id ? Company::query()->find($record->company_id) : null) ?? $record->sourceBranch?->defaultCompany();

        $pdf = Pdf::loadView('pdf.commission-po', [
            'companyName' => strtoupper($company?->name ?? $record->sourceBranch?->company_name ?? 'O & G TRANSPORT'),
            'po' => [
                'number' => $record->purchaseOrder?->po_number ?? $record->number,
                'date' => ($record->batch?->cutoff_date ?? $record->created_at)?->format('d/m/Y'),
                'lorry_group' => (string) ($driver?->lorry_group_code ?: $driver?->lorry_group ?: ''),
                'supplier_code' => $supplierCode,
                'supplier_name' => strtoupper((string) ($driver?->lorry_company ?: $driver?->name ?: '')),
                'gst' => '',
            ],
            'sections' => $sections,
            'total' => round((float) $sections->sum('subtotal'), 2),
        ])->setPaper('a4');

        return $pdf->stream($record->number.'.pdf');
    }

    /** The supplier of a slip: the lorry company code of the lorry's default driver (else the slip's driver). */
    private function supplierCode(CommissionSlip $slip): string
    {
        $driver = $slip->lorry?->defaultDriver ?? $slip->driver;

        return strtoupper(trim((string) ($driver?->lorry_company_code ?? '')));
    }

    private function jobDate(CommissionLineItem $line): string
    {
        $date = $line->consignmentNote?->issued_at ?? $line->deliveryOrder?->created_at ?? $line->created_at;

        return $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '';
    }
}
