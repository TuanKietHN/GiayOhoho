<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSizeGuide extends Model
{
    protected $table = 'product_size_guides';

    protected $fillable = [
        'product_id',
        'brand',
        'product_type',
        'title',
        'measurement_unit',
        'measurement_instructions',
        'fit_notes',
        'size_chart',
        'is_active',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
