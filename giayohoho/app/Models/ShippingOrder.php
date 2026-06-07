<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingOrder extends Model
{
    protected $table = 'shipping_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'expected_delivery_time' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_id');
    }
}
