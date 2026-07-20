<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'quantity',
        'low_stock_threshold',
    ];

    protected $casts = [
        'quantity'            => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * 是否為低庫存
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->low_stock_threshold && $this->quantity > 0;
    }

    /**
     * 是否缺貨
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity <= 0;
    }

    /**
     * 庫存狀態
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->quantity <= 0) return 'out_of_stock';
        if ($this->quantity <= $this->low_stock_threshold) return 'low_stock';
        return 'in_stock';
    }
}
