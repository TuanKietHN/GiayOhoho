<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentDetail extends Model
{
    protected $table = 'payment_details';

    protected $fillable = [
        'order_id',
        'amount',
        'provider',
        'status',
        'transaction_id',
        'provider_data',
        'webhook_raw',
        'webhook_idempotency_key',
        'return_url',
        'cancel_url',
        'expires_at',
        'version',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class, 'payment_id');
    }
}
