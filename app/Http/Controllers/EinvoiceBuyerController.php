<?php

namespace App\Http\Controllers;

use App\Domains\Integration\Actions\UpdateEinvoiceBuyerInfo;
use App\Domains\Integration\Models\EinvoiceSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EinvoiceBuyerController extends Controller
{
    public function show(string $token): View
    {
        $submission = EinvoiceSubmission::query()
            ->with('invoice.customer')
            ->where('buyer_token', $token)
            ->firstOrFail();

        return view('einvoice.buyer-form', [
            'submission' => $submission,
            'buyer' => $submission->buyer_info ?? [],
        ]);
    }

    public function store(string $token, Request $request, UpdateEinvoiceBuyerInfo $action)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'brn' => ['nullable', 'string', 'max:50'],
            'id_type' => ['nullable', 'string', 'max:30'],
            'id_value' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $action->executeByToken($token, $data);

        return redirect()
            ->route('einvoice.buyer.show', $token)
            ->with('status', 'Buyer information saved. The office can now submit this e-invoice.');
    }
}
