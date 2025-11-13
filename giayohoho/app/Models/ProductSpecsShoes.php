<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecsShoes extends Model
{
    protected $table = 'product_specs_shoes';

    protected $fillable = [
        'product_id',
        'cushioning_level',
        'pronation_type',
        'drop_mm',
        'weight_grams',
        'is_waterproof',
        'is_reflective',
        'upper_material',
        'midsole_technology',
        'outsole_technology',
    ];

    protected $casts = [
        'drop_mm' => 'float',
        'weight_grams' => 'integer',
        'is_waterproof' => 'boolean',
        'is_reflective' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
