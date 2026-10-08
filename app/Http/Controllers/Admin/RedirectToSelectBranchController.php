<?php

namespace App\Http\Controllers\Admin;

use App\Support\DefaultBranch;
use Illuminate\Http\RedirectResponse;

/**
 * /admin (no tenant in the URL) and the old /admin/select-branch link: go straight to the
 * user's branch dashboard. Branches are switched from the top-bar branch switcher.
 */
class RedirectToSelectBranchController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->to(DefaultBranch::homeUrl());
    }
}
