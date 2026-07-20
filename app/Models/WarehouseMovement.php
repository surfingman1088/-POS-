<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseMovement extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'type',
        'source',
        'destination',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'before_quantity' => 'integer',
        'after_quantity'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 異動類型中文標籤
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'receipt'          => '入庫',
            'dispatch'         => '出庫',
            'stocktake_adjust' => '盤點調整',
            'manual'           => '手動調整',
            default            => $this->type,
        };
    }
}
