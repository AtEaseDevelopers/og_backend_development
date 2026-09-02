<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class CreditApprovalDemoSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private array $applications = [
        [
            'customer_code' => 'CUST-KL-010',
            'branch_code' => 'KL',
            'requested_amount' => 250000,
            'status' => 'pending',
            'reason' => 'Credit limit exceeded for new regional distribution contract.',
            'assessment_notes' => 'Customer has been operating for 5 years with stable revenue. Recommend approving MYR 200,000 instead of requested MYR 250,000 to manage exposure while supporting growth.',
            'documents' => [
                ['name' => 'Bank Statement - May.pdf', 'size' => '2.4 MB', 'type' => 'pdf'],
                ['name' => 'Business Reg.pdf', 'size' => '1.2 MB', 'type' => 'pdf'],
                ['name' => '3-Year Financial Summary.csv', 'size' => '450 KB', 'type' => 'csv'],
            ],
        ],
        [
            'customer_code' => 'CUST-KL-011',
            'branch_code' => 'KL',
            'requested_amount' => 85000,
            'status' => 'pending',
            'reason' => 'Customer has overdue invoices beyond approved credit term.',
            'assessment_notes' => 'Outstanding invoices from last month need clarification before approval.',
        ],
        [
            'customer_code' => 'CUST-JB-010',
            'branch_code' => 'JB',
            'requested_amount' => 120000,
            'status' => 'approved',
            'reason' => 'No approved preset pricing for requested item/route.',
            'assessment_notes' => 'Approved with limit MYR 120,000 after pricing review.',
        ],
        [
            'customer_code' => 'CUST-KLG-010',
            'branch_code' => 'KLG',
            'requested_amount' => 45000,
            'status' => 'rejected',
            'reason' => 'Credit limit exceeded (limit RM 30,000, outstanding RM 18,500, this quote RM 45,000).',
            'assessment_notes' => 'Rejected due to insufficient supporting documents and high utilization.',
        ],
    ];

    public function run(): void
    {
        $requester = User::query()->where('email', 'manager@demo.local')->first()
            ?? User::query()->where('email', 'hq@demo.local')->first()
            ?? User::query()->first();

        if (! $requester) {
            $this->command?->warn('No users found. Run DatabaseSeeder first.');

            return;
        }

        foreach ($this->applications as $application) {
            $branch = Branch::query()->where('code', $application['branch_code'])->first();
            $customer = Customer::query()->where('code', $application['customer_code'])->first();

            if (! $branch || ! $customer) {
                continue;
            }

            $companyId = $branch->companies()->first()?->id ?? $customer->company_id;

            $quotationNumber = 'Q-DEMO-CR-'.strtoupper(str_replace('-', '', $application['customer_code']));

            $quotation = Quotation::query()->updateOrCreate(
                ['number' => $quotationNumber],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'salesperson_id' => $requester->id,
                    'status' => $application['status'] === 'pending'
                        ? QuotationStatus::PendingApproval
                        : ($application['status'] === 'approved' ? QuotationStatus::Confirmed : QuotationStatus::Rejected),
                    'pricing_source' => 'manual',
                    'valid_until' => now()->addDays(14),
                    'subtotal' => $application['requested_amount'],
                    'total_amount' => $application['requested_amount'],
                    'notes' => 'Demo credit approval quotation',
                    'created_by' => $requester->id,
                    'confirmed_at' => $application['status'] === 'approved' ? now()->subDays(2) : null,
                    'rejection_reason' => $application['status'] === 'rejected' ? 'Credit approval rejected' : null,
                ],
            );

            $triggerDetails = [
                'reasons' => [$application['reason']],
                'assessment_notes' => $application['assessment_notes'] ?? null,
            ];

            if (isset($application['documents'])) {
                $triggerDetails['documents'] = $application['documents'];
            }

            CreditApprovalRequest::query()->updateOrCreate(
                [
                    'quotation_id' => $quotation->id,
                ],
                [
                    'customer_id' => $customer->id,
                    'company_id' => $companyId,
                    'branch_id' => $branch->id,
                    'reason' => $application['reason'],
                    'requested_amount' => $application['requested_amount'],
                    'trigger_details' => $triggerDetails,
                    'status' => $application['status'],
                    'requested_by' => $requester->id,
                    'approved_by' => in_array($application['status'], ['approved', 'rejected'], true) ? $requester->id : null,
                    'remarks' => $application['assessment_notes'] ?? null,
                    'decided_at' => in_array($application['status'], ['approved', 'rejected'], true) ? now()->subDay() : null,
                ],
            );
        }

        $this->command?->info('Credit approval demo applications seeded.');
    }
}
