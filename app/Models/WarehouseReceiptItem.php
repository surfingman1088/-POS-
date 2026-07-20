<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceiptItem extends Model
{
    protected $fillable = [
        'receipt_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity'  => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class, 'receipt_id');
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
