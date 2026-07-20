<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceipt extends Model
{
    protected $fillable = [
        'receipt_no',
        'supplier_name',
        'supplier_contact',
        'receipt_date',
        'batch_no',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseReceiptItem::class, 'receipt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 產生入庫單號
     */
    public static function generateReceiptNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "WR-{$date}-";
        $last   = static::where('receipt_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('receipt_no');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
