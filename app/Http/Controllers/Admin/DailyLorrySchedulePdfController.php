<?php

namespace App\Http\Controllers\Admin;

use App\Support\DailyLorryScheduleData;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** Printable daily lorry schedule, laid out like the manual "DATE / O&G JB" sheet. */
class DailyLorrySchedulePdfController
{
    public function __invoke(Request $request, string $tenant): Response
    {
        abort_unless(Filament::auth()->check(), 403);

        $date = $request->query('date') && strtotime((string) $request->query('date'))
            ? Carbon::parse((string) $request->query('date'))
            : now();
        $branch = $request->query('branch');
        $branchId = $branch && $branch !== 'all' ? (int) $branch : null;

        $schedule = app(DailyLorryScheduleData::class)->for($date, $branchId);

        $pdf = Pdf::loadView('pdf.daily-lorry-schedule', ['schedule' => $schedule])->setPaper('a4');

        return $pdf->stream('lorry-schedule-'.$date->format('Ymd').'.pdf');
    }
}
