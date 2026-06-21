<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use SoftDeletes;

    protected $table = 'reviews';

    protected $fillable = [
        'account_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
        'status',
        'shop_reply',
        'moderated_at',
        'moderated_by',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
