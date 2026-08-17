<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Filament\Resources\CustomerResource\Schemas\CustomerForm;
use App\Http\Controllers\Controller;
use App\Support\PortalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanySelectionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $branch = PortalSelection::branch();

        if (! $branch) {
            return redirect()->route('portal.select-branch');
        }

        return view('portal.select-company', [
            'branch' => $branch,
            'companies' => $request->user()->accessiblePortalCompanies($branch),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = PortalSelection::branch();
        abort_unless($branch, 403);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($request->user()->canAccessPortalCompany($company), 403);
        abort_unless($company->branch_id === $branch->id, 403);

        PortalSelection::setCompany($company);

        return redirect()->route('portal.dashboard');
    }

    public function register(Request $request): RedirectResponse
    {
        $branch = PortalSelection::branch();
        abort_unless($branch instanceof Branch, 403);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:255'],
            'brn' => [
                'required',
                'string',
                'max:100',
                'unique:companies,brn,NULL,id,branch_id,'.$branch->id,
            ],
            'tin' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $code = Str::upper(trim($data['code']));

        $company = DB::transaction(function () use ($user, $branch, $data, $code) {
            $company = Company::query()->create([
                'branch_id' => $branch->id,
                'code' => $code,
                'name' => $data['name'],
                'brn' => $data['brn'],
                'tin' => $data['tin'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'is_active' => true,
                'registered_by' => $user->id,
            ]);

            $customer = Customer::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => CustomerForm::generateDebtorCode($data['name']),
                'company_name' => $data['name'],
                'brn' => $data['brn'],
                'tin' => $data['tin'] ?? null,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
                'address' => $data['address'] ?? null,
                'status' => 'active',
                'portal_approved' => true,
            ]);

            $user->customers()->syncWithoutDetaching([
                $customer->id => ['status' => 'approved'],
            ]);

            if (! $user->customer_id) {
                $user->update(['customer_id' => $customer->id]);
            }

            return $company;
        });

        PortalSelection::setCompany($company);

        return redirect()
            ->route('portal.dashboard')
            ->with('status', "Company {$company->name} registered.");
    }
}
