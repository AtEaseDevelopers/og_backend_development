<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Http\Controllers\Controller;
use App\Support\PortalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchSelectionController extends Controller
{
    public function show(Request $request): View
    {
        return view('portal.select-branch', [
            'branches' => $request->user()->accessiblePortalBranches(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessPortalBranch($branch), 403);

        PortalSelection::setBranch($branch);

        return redirect()->route('portal.select-company');
    }

    public function reset(Request $request): RedirectResponse
    {
        PortalSelection::clear();

        return redirect()->route('portal.select-branch');
    }
}
