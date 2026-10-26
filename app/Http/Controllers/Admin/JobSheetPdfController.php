<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Company;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Job Sheet print in the client's layout (user, 27 Oct 2026, from their "JUK 9293 Job Sheet" examples): one
 * job sheet (a lorry's day) printed as one document per job sheet code — "Self" (the lorry's own deliveries, Job No.
 * as is) and one per transfer code of its subsheets (Job No. + the code, e.g. 0443O own collection, 0443J transfer
 * to a small lorry) — each with No. | CSM No. | Bill No. | From | To | Consignor | Consignee | Qty U/M | Advance
 * Taken rows, the totals and a signature line. Every document is a page of the PDF.
 */
class JobSheetPdfController
{
    public function __invoke(string $tenant, int|string $jobSheet): Response
    {
        abort_unless(Filament::auth()->user(), 403);

        $sheet = JobSheet::query()->with(['lorry.defaultDriver', 'driver', 'operatingBranch', 'deliveryOrders'])->findOrFail($jobSheet);
        $companyId = CurrentCompany::id();
        abort_if($companyId && (int) $sheet->company_id !== (int) $companyId, 404);

        $dos = $sheet->deliveryOrders->sortBy('id')->values();
        $subsheets = Subsheet::query()->whereIn('id', $dos->pluck('subsheet_id')->filter()->all())->get()->keyBy('id');
        $csns = ConsignmentNote::query()
            ->with(['fromLocation:id,name', 'toLocation:id,name', 'lines'])
            ->whereIn('id', $dos->pluck('consignment_note_id')->filter()->all())
            ->get()
            ->keyBy('id');

        // the documents: Self (no code) first, then one per transfer code, in code order
        $groups = $dos
            ->groupBy(fn ($do) => $do->subsheet_id ? strtoupper((string) ($subsheets[$do->subsheet_id]?->transfer_code ?? '')) : '')
            ->sortKeys()
            ->map(function ($group, string $code) use ($csns, $subsheets) {
                $rows = $group->map(function ($do, int $i) use ($csns, $subsheets) {
                    $csn = $csns[$do->consignment_note_id] ?? null;
                    $qty = $csn ? (float) $csn->lines->sum('quantity') : 0.0;
                    $units = $csn ? $csn->lines->pluck('uom')->filter()->unique()->implode('/') : '';

                    return [
                        'csn' => (string) ($csn?->number ?? $do->number),
                        'bill' => (string) ($csn?->invoice_number ?? ''),
                        'from' => (string) ($csn?->fromLocation?->name ?? ''),
                        'to' => (string) ($csn?->toLocation?->name ?? ''),
                        'consignor' => (string) ($csn?->consignor_name ?? $csn?->customer_name ?? ''),
                        'consignee' => (string) ($csn?->consignee_name ?? ''),
                        'qty' => $qty,
                        'uom' => $units,
                        // the amount the driver collects on the way (a COD CSN with the advance taken)
                        'advance' => $csn && $csn->advance_taken ? (float) $csn->total_amount : null,
                        'note' => $do->subsheet_id ? (string) ($subsheets[$do->subsheet_id]?->notes ?? '') : '',
                    ];
                })->values();

                return [
                    'code' => $code,
                    'title' => $code === '' ? 'Self' : $code,
                    'rows' => $rows->all(),
                    'qty_total' => round((float) $rows->sum('qty'), 2),
                    'advance_total' => round((float) $rows->sum('advance'), 2),
                ];
            })
            ->values();

        $driver = $sheet->driver ?? $sheet->lorry?->defaultDriver;
        $company = ($sheet->company_id ? Company::query()->find($sheet->company_id) : null) ?? $sheet->operatingBranch?->defaultCompany();

        $pdf = Pdf::loadView('pdf.job-sheet', [
            'companyName' => strtoupper($company?->name ?? $sheet->operatingBranch?->company_name ?? 'O & G TRANSPORT'),
            'sheet' => [
                'number' => $sheet->number,
                'date' => $sheet->operating_date?->format('d/m/Y'),
                'lorry' => (string) ($sheet->lorry?->registration_no ?? ''),
                'type' => (string) ($sheet->lorry?->type ?? ''),
                'lorry_company' => strtoupper((string) ($driver?->lorry_company ?? '')),
                'driver' => strtoupper((string) ($driver?->name ?? '')),
                'driver_tel' => (string) ($driver?->phone ?? ''),
                'branch' => (string) ($sheet->operatingBranch?->code ?? ''),
            ],
            'printedAt' => now()->format('d/m/Y').' Time: '.now()->format('H:i'),
            'groups' => $groups->isEmpty() ? collect([['code' => '', 'title' => 'Self', 'rows' => [], 'qty_total' => 0.0, 'advance_total' => 0.0]]) : $groups,
        ])->setPaper('a4');

        return $pdf->stream($sheet->number.'.pdf');
    }
}
