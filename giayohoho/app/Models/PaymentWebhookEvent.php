<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    protected $table = 'payment_webhook_events';

    public $timestamps = false;

    protected $fillable = [
        'provider',
        'event_key',
        'payment_id',
        'status',
        'payload',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentDetail::class, 'payment_id');
    }
}
