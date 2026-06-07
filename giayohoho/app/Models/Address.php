<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $table = 'addresses';

    protected $fillable = [
        'account_id',
        'address_line',
        'ward',
        'district',
        'city',
        'country',
        'postal_code',
        'ghn_province_id',
        'ghn_district_id',
        'ghn_ward_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }
}
