<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingEvent extends Model
{
    protected $table = 'shipping_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
