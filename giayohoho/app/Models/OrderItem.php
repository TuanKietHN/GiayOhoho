<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_item';

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_id_snapshot',
        'product_name_snapshot',
        'product_slug_snapshot',
        'variant_id_snapshot',
        'variant_sku_snapshot',
        'variant_size_snapshot',
        'variant_color_snapshot',
        'image_url_snapshot',
        'image_object_name_snapshot',
        'quantity',
        'price',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
