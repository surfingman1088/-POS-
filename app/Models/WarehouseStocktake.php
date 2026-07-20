<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStocktake extends Model
{
    protected $fillable = [
        'stocktake_no',
        'type',
        'branch_id',
        'stocktake_date',
        'notes',
        'status',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'stocktake_date' => 'date',
        'confirmed_at'   => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseStocktakeItem::class, 'stocktake_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * 產生盤點單號
     */
    public static function generateStocktakeNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "ST-{$date}-";
        $last   = static::where('stocktake_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('stocktake_no');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
