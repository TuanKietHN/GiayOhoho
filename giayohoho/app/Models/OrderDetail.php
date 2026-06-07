<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    protected $table = 'order_details';

    protected $fillable = [
        'account_id',
        'total',
        'sub_total',
        'discount_amount',
        'coupon_id',
        'order_address',
        'recipient_name',
        'recipient_phone',
        'contact_email',
        'payment_method',
        'shipping_provider',
        'shipping_fee',
        'shipping_discount',
        'shipping_service_id',
        'shipping_service_type_id',
        'shipping_quote_id',
        'ghn_to_province_id',
        'ghn_to_district_id',
        'ghn_to_ward_code',
        'expected_delivery_time',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'expected_delivery_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
