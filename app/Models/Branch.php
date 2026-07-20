<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 分店庫存
    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    // 出庫單
    public function dispatches(): HasMany
    {
        return $this->hasMany(WarehouseDispatch::class);
    }

    // 盤點單
    public function stocktakes(): HasMany
    {
        return $this->hasMany(WarehouseStocktake::class);
    }

    // 關聯使用者
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches');
    }

    /**
     * 預設 6 間分店資料（用於 Seeder）
     */
    public static function defaultBranches(): array
    {
        return [
            ['name' => '八德店', 'code' => 'bade',   'address' => '桃園市八德區'],
            ['name' => '三峽店', 'code' => 'sanxia', 'address' => '新北市三峽區'],
            ['name' => '大竹店', 'code' => 'dazhu',  'address' => '桃園市蘆竹區大竹'],
            ['name' => '林口店', 'code' => 'linkou', 'address' => '新北市林口區'],
            ['name' => '藝文店', 'code' => 'yiwen',  'address' => '桃園市中壢區藝文'],
            ['name' => '菓林店', 'code' => 'guolin', 'address' => '桃園市龜山區'],
        ];
    }
}
