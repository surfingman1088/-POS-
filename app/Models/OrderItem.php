<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'refunded_quantity',  // tracks how many units have been returned
        'unit_price',
        'discount_amount',
        'total_price',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'refunded_quantity' => 'integer',
        'unit_price'        => 'float',
        'discount_amount'   => 'float',
        'total_price'       => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 訂單項目的商品規格
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * 取得顯示名稱（含規格）
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->product?->name ?? '(deleted)';
        if ($this->variant_id && $this->variant) {
            $name .= ' - ' . $this->variant->name;
        }
        return $name;
    }

    /**
     * Units that can still be refunded in a future refund.
     */
    public function getReturnableAttribute(): int
    {
        return max(0, $this->quantity - ($this->refunded_quantity ?? 0));
    }

    /**
     * Whether this line item has been fully returned.
     */
    public function getIsFullyRefundedAttribute(): bool
    {
        return ($this->refunded_quantity ?? 0) >= $this->quantity;
    }
}
