<?php

namespace App\Domains\Integration\Models;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EinvoiceSubmission extends Model
{
    protected $fillable = [
        'invoice_id', 'status', 'submission_mode', 'uuid', 'validated_pdf_path',
        'buyer_info', 'buyer_token', 'response_payload', 'retry_count',
        'submitted_at', 'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'buyer_info' => 'array',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function publicBuyerUrl(): string
    {
        return url('/einvoice-buyer/'.$this->buyer_token);
    }
}
