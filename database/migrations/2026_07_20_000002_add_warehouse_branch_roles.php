<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 新增 warehouse（倉庫管理員）和 branch（分店人員）角色
     * 現有角色：admin, staff
     * 新增角色：warehouse, branch
     *
     * 注意：role 欄位為 string，無需修改欄位定義，只需確保應用層支援新值
     */
    public function up(): void
    {
        // role 欄位已是 string，無需修改資料表結構
        // 此 migration 作為版本記錄，確保新角色已被系統認可
        // 實際角色控制在 User 模型和 Middleware 中處理
    }

    public function down(): void
    {
        // 若需回滾，可將 warehouse/branch 角色的使用者改回 staff
        \App\Models\User::whereIn('role', ['warehouse', 'branch'])->update(['role' => 'staff']);
    }
};
