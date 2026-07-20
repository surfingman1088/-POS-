<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStocktakeItem extends Model
{
    protected $fillable = [
        'stocktake_id',
        'product_id',
        'variant_id',
        'system_quantity',
        'actual_quantity',
        'difference',
        'notes',
    ];

    protected $casts = [
        'system_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'difference'      => 'integer',
    ];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(WarehouseStocktake::class, 'stocktake_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
