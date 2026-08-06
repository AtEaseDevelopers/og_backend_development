<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsolidateTermInvoices
{
    public function __construct(private DocumentNumberingService $numbering) {}

    /**
     * Consolidate delivered term CSNs for a billing month.
     * One invoice per customer per source branch.
     *
     * @return Collection<int, Invoice>
     */
    public function execute(string $billingMonth, ?int $branchId = null): Collection
    {
        [$year, $month] = explode('-', $billingMonth);

        $invoicedCsnIds = Invoice::query()
            ->where('type', 'term')
            ->where('billing_month', $billingMonth)
            ->with('lines')
            ->get()
            ->flatMap(fn (Invoice $invoice) => $invoice->lines->pluck('consignment_note_id'))
            ->filter()
            ->unique()
            ->all();

        $query = ConsignmentNote::query()
            ->with(['lines', 'sourceBranch', 'customer', 'deliveryOrder'])
            ->where('billing_type', CsnBillingType::Term)
            ->where('status', CsnStatus::Delivered)
            ->when($invoicedCsnIds !== [], fn ($q) => $q->whereNotIn('id', $invoicedCsnIds))
            ->whereHas('deliveryOrder', function ($q) use ($year, $month) {
                $q->whereYear('delivered_at', $year)->whereMonth('delivered_at', $month);
            });

        if ($branchId) {
            $query->where('source_branch_id', $branchId);
        }

        $groups = $query->get()->groupBy(fn (ConsignmentNote $csn) => $csn->source_branch_id.'-'.$csn->customer_id);

        return DB::transaction(function () use ($groups, $billingMonth) {
            $invoices = collect();

            foreach ($groups as $csns) {
                /** @var ConsignmentNote $first */
                $first = $csns->first();
                $customer = Customer::query()->findOrFail($first->customer_id);
                $branch = Branch::query()->findOrFail($first->source_branch_id);

                $subtotal = (float) $csns->sum('subtotal');
                $tax = (float) $csns->sum('tax_amount');
                $rawTotal = $subtotal + $tax;
                $rounded = round($rawTotal, 2);
                $rounding = round($rounded - $rawTotal, 2);

                $dueDate = $customer->credit_term_days > 0
                    ? now()->addDays($customer->credit_term_days)->toDateString()
                    : now()->toDateString();

                $invoice = Invoice::query()->create([
                    'number' => $this->numbering->next($branch, DocumentType::Invoice),
                    'source_branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'type' => 'term',
                    'billing_month' => $billingMonth,
                    'status' => InvoiceStatus::Outstanding->value,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'rounding_amount' => $rounding,
                    'total_amount' => $rounded,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => $dueDate,
                ]);

                foreach ($csns as $csn) {
                    $invoice->lines()->create([
                        'consignment_note_id' => $csn->id,
                        'delivery_order_id' => $csn->deliveryOrder?->id,
                        'description' => 'CSN '.$csn->number.' — '.$csn->delivery_address,
                        'amount' => $csn->total_amount,
                    ]);
                }

                $invoices->push($invoice->load('lines'));
            }

            return $invoices;
        });
    }
}
