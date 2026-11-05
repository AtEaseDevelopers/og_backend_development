<?php

namespace Database\Seeders;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\JobSheetTask;
use App\Domains\Dispatch\Models\ProfitSharingTransaction;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A fresh Draft trip for today for driver.kl@og.local to walk through the driver app (section O): a term
 * delivery, a COD delivery, a transfer-coded delivery and an additional PSI pickup task. Re-run for a new trip.
 */
class DriverAppDemoSeeder extends Seeder
{
    public function run(): void
    {
        $numbering = app(DocumentNumberingService::class);
        $user = User::query()->where('email', 'driver.kl@og.local')->firstOrFail();
        $driver = $user->driver;
        $lorry = Lorry::query()->with('branch')->where('default_driver_id', $driver->id)->firstOrFail();
        $mainLorry = Lorry::query()->where('branch_id', $lorry->branch_id)->whereKeyNot($lorry->id)->where('is_active', true)->firstOrFail();
        $branch = $lorry->branch;
        $template = ConsignmentNote::query()->with('lines')->where('source_branch_id', $branch->id)->orderBy('id')->firstOrFail();
        $today = now()->toDateString();

        DB::transaction(function () use ($numbering, $driver, $lorry, $mainLorry, $branch, $template, $today) {
            $jobSheet = JobSheet::query()->create([
                'number' => $numbering->next($branch, DocumentType::JobSheet),
                'company_id' => $lorry->company_id,
                'operating_branch_id' => $branch->id,
                'lorry_id' => $lorry->id,
                'driver_id' => $driver->id,
                'operating_date' => $today,
                'trip_no' => (int) JobSheet::query()->where('lorry_id', $lorry->id)->whereDate('operating_date', $today)->max('trip_no') + 1,
                'status' => JobSheetStatus::Draft,
            ]);

            $stops = [
                ['consignee' => 'Shah Alam Hardware', 'address' => '12 Jalan Kilang 3, 40000 Shah Alam', 'postcode' => '40000', 'city' => 'Shah Alam', 'state' => 'Selangor', 'billing' => CsnBillingType::Term, 'total' => 280],
                ['consignee' => 'Klang Furniture Mart', 'address' => '7 Jalan Meru, 41050 Klang', 'postcode' => '41050', 'city' => 'Klang', 'state' => 'Selangor', 'billing' => CsnBillingType::Cod, 'total' => 350],
                ['consignee' => 'JB Distribution Hub', 'address' => '3 Jalan Perindustrian, 81400 Senai', 'postcode' => '81400', 'city' => 'Senai', 'state' => 'Johor', 'billing' => CsnBillingType::Term, 'total' => 520, 'transfer' => 'TRF-KL-JB'],
            ];

            foreach ($stops as $i => $stop) {
                $csn = $this->csn($numbering, $template, $branch, $stop);
                $do = $this->deliveryOrder($numbering, $csn, $jobSheet, $lorry->id, $driver->id);
                JobSheetTask::query()->create(['job_sheet_id' => $jobSheet->id, 'delivery_order_id' => $do->id, 'sequence' => $i + 1, 'route_group' => $stop['state']]);
            }

            // Additional task: driver.kl collects goods for a CSN carried by another lorry and hands them over (PSI)
            $csn = $this->csn($numbering, $template, $branch, ['consignee' => 'Petaling Jaya Retail', 'address' => '22 Jalan SS2/24, 47300 Petaling Jaya', 'postcode' => '47300', 'city' => 'Petaling Jaya', 'state' => 'Selangor', 'billing' => CsnBillingType::Term, 'total' => 410]);
            $main = $this->deliveryOrder($numbering, $csn, null, $mainLorry->id, $mainLorry->default_driver_id);

            $subsheet = Subsheet::query()->create([
                'number' => $numbering->next($branch, DocumentType::Subsheet),
                'job_sheet_id' => $jobSheet->id,
                'consignment_note_id' => $csn->id,
                'task_type' => 'incoming_psi',
                'notes' => 'Collect from supplier and hand over to '.$mainLorry->registration_no,
                'main_driver_id' => $main->driver_id,
                'main_lorry_id' => $mainLorry->id,
                'sub_driver_id' => $driver->id,
                'sub_lorry_id' => $lorry->id,
                'segment_route' => 'Supplier (Sungai Buloh) → KL depot',
                'handover_status' => 'pending',
                'psi_amount' => 30,
                'pso_amount' => 30,
            ]);

            $subDo = $this->deliveryOrder($numbering, $csn, $jobSheet, $lorry->id, $driver->id, $main->id, $subsheet->id);
            $subsheet->update(['delivery_order_id' => $subDo->id]);
            JobSheetTask::query()->create(['job_sheet_id' => $jobSheet->id, 'delivery_order_id' => $subDo->id, 'sequence' => count($stops) + 1, 'route_group' => 'Selangor']);

            $psi = ProfitSharingTransaction::query()->create([
                'source_branch_id' => $branch->id,
                'delivery_order_id' => $subDo->id,
                'consignment_note_id' => $csn->id,
                'assisting_driver_id' => $driver->id,
                'main_driver_id' => $main->driver_id,
                'psi_amount' => 30,
                'pso_amount' => 30,
                'status' => 'pending',
            ]);
            $subsheet->update(['profit_sharing_transaction_id' => $psi->id]);

            $this->command?->info("Driver app demo trip {$jobSheet->number} ({$jobSheet->tripLabel()}) for {$driver->name} on {$today}.");
        });
    }

    private function csn(DocumentNumberingService $numbering, ConsignmentNote $template, $branch, array $stop): ConsignmentNote
    {
        $csn = $template->replicate(['number', 'qr_token', 'tracking_token', 'invoice_number', 'proforma_number', 'claimed_by', 'claimed_at']);
        $csn->fill([
            'number' => $numbering->next($branch, DocumentType::Csn),
            'qr_token' => (string) Str::uuid(),
            'tracking_token' => Str::random(40),
            'status' => CsnStatus::Assigned,
            'return_status' => 'not_required',
            'quotation_destination_id' => null,
            'do_number' => null,
            'job_no' => null,
            'payment_status' => $stop['billing'] === CsnBillingType::Cod ? 'cod_pending' : $template->payment_status,
            'billing_type' => $stop['billing'],
            'consignee_name' => $stop['consignee'],
            'consignee_pic' => 'Receiving Clerk',
            'consignee_phone' => '0123456789',
            'delivery_address' => $stop['address'],
            'delivery_postcode' => $stop['postcode'],
            'delivery_city' => $stop['city'],
            'delivery_state' => $stop['state'],
            'subtotal' => $stop['total'],
            'tax_amount' => 0,
            'total_amount' => $stop['total'],
            'quotation_id' => null,
            'transfer_code_id' => isset($stop['transfer']) ? TransferCode::query()->where('code', $stop['transfer'])->value('id') : null,
            'other_do_numbers' => null,
            'issued_at' => now(),
        ]);
        $csn->save();

        foreach ($template->lines as $line) {
            $copy = $line->replicate();
            $copy->consignment_note_id = $csn->id;
            $copy->save();
        }

        return $csn;
    }

    private function deliveryOrder(DocumentNumberingService $numbering, ConsignmentNote $csn, ?JobSheet $jobSheet, int $lorryId, ?int $driverId, ?int $parentId = null, ?int $subsheetId = null): DeliveryOrder
    {
        return DeliveryOrder::query()->create([
            'number' => $numbering->next($csn->sourceBranch, DocumentType::Do),
            'company_id' => $csn->company_id,
            'consignment_note_id' => $csn->id,
            'source_branch_id' => $csn->source_branch_id,
            'job_sheet_id' => $jobSheet?->id,
            'lorry_id' => $lorryId,
            'driver_id' => $driverId,
            'status' => DeliveryOrderStatus::Assigned,
            'tracking_token' => Str::random(40),
            'parent_do_id' => $parentId,
            'subsheet_id' => $subsheetId,
        ]);
    }
}
