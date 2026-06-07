<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $table = 'payment_events';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'from_status',
        'to_status',
        'reason',
        'raw_data',
        'created_at',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentDetail::class, 'payment_id');
    }
}
