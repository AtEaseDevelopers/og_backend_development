<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;

class RedirectToSelectBranchController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('filament.admin.select-branch');
    }
}
