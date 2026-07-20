<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseDispatch extends Model
{
    protected $fillable = [
        'dispatch_no',
        'branch_id',
        'dispatch_date',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseDispatchItem::class, 'dispatch_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 產生出庫單號
     */
    public static function generateDispatchNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "WD-{$date}-";
        $last   = static::where('dispatch_no', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('dispatch_no');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
