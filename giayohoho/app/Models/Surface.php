<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Surface extends Model
{
    protected $table = 'surfaces';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public $timestamps = false;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_surfaces', 'surface_id', 'product_id');
    }
}
