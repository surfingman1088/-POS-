<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'type',
        'price',
        'stocks',
        'sold',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'stocks'     => 'integer',
        'sold'       => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 所屬商品
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 訂單項目
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    /**
     * 取得實際售價（若規格未設定則繼承商品主價格）
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        return (float) ($this->product->price ?? 0);
    }

    /**
     * 庫存狀態
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stocks <= 0) {
            return 'out_of_stock';
        } elseif ($this->stocks < 10) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * 規格類型的中文標籤
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'color'   => '顏色',
            'size'    => '尺寸',
            'flavor'  => '口味',
            'general' => '規格',
            default   => '規格',
        };
    }

    /**
     * 所有可用規格類型
     */
    public static function types(): array
    {
        return [
            'general' => '一般規格',
            'color'   => '顏色',
            'size'    => '尺寸',
            'flavor'  => '口味',
        ];
    }
}
