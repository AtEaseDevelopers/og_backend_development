@include('filament.forms.quotation-body-preview', [
    'title' => 'Transport Charges',
    'terms' => filled($chargeColumn ?? null) ? 'Billing destination: '.$chargeColumn : 'Per destination rates',
    'rateMatrix' => $rateMatrix,
])
