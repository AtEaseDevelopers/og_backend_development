@include('filament.forms.quotation-body-preview', [
    'title' => 'Transport Charges',
    'terms' => filled($chargeColumn ?? null) ? 'Billing area: '.$chargeColumn : 'Per area rates',
    'rateMatrix' => $rateMatrix,
])
