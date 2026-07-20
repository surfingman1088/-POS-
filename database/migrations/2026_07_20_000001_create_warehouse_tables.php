<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 倉儲管理模組相關資料表
     */
    public function up(): void
    {
        // ── 1. 分店資料表（對應 6 間門市）──────────────────────────────
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // 分店名稱，如「八德店」
            $table->string('code')->unique();          // 分店代碼，如「bade」
            $table->string('address')->nullable();     // 地址
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── 2. 倉庫主庫存（中央倉庫的商品庫存）────────────────────────
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index(); // 支援規格
            $table->integer('quantity')->default(0);   // 倉庫現有庫存
            $table->integer('low_stock_threshold')->default(10); // 低庫存警示門檻
            $table->timestamps();

            $table->unique(['product_id', 'variant_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });

        // ── 3. 分店庫存（各分店持有的商品庫存）────────────────────────
        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->integer('quantity')->default(0);   // 分店現有庫存
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'variant_id']);
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });

        // ── 4. 入庫單（廠商進貨）──────────────────────────────────────
        Schema::create('warehouse_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();    // 入庫單號，如 WR-20260720-001
            $table->string('supplier_name')->nullable(); // 廠商名稱
            $table->string('supplier_contact')->nullable(); // 廠商聯絡方式
            $table->date('receipt_date');              // 入庫日期
            $table->string('batch_no')->nullable();    // 批次號碼
            $table->text('notes')->nullable();         // 備註
            $table->string('status')->default('completed'); // pending / completed
            $table->unsignedBigInteger('created_by');  // 建立人
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
        });

        // ── 5. 入庫單明細 ──────────────────────────────────────────────
        Schema::create('warehouse_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->integer('quantity');               // 入庫數量
            $table->decimal('unit_cost', 10, 2)->nullable(); // 進貨單價
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('warehouse_receipts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });

        // ── 6. 出庫單（撥貨到各分店）──────────────────────────────────
        Schema::create('warehouse_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_no')->unique();   // 出庫單號，如 WD-20260720-001
            $table->unsignedBigInteger('branch_id')->index(); // 目標分店
            $table->date('dispatch_date');             // 出庫日期
            $table->text('notes')->nullable();
            $table->string('status')->default('completed'); // pending / completed
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // ── 7. 出庫單明細 ──────────────────────────────────────────────
        Schema::create('warehouse_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispatch_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->integer('quantity');               // 出庫數量
            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('warehouse_dispatches')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });

        // ── 8. 盤點單 ──────────────────────────────────────────────────
        Schema::create('warehouse_stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('stocktake_no')->unique(); // 盤點單號，如 ST-20260720-001
            $table->string('type')->default('warehouse'); // warehouse / branch
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // 若為分店盤點
            $table->date('stocktake_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft'); // draft / confirmed
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
        });

        // ── 9. 盤點單明細 ──────────────────────────────────────────────
        Schema::create('warehouse_stocktake_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stocktake_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->integer('system_quantity');        // 系統庫存
            $table->integer('actual_quantity');        // 實際盤點數量
            $table->integer('difference');             // 差異（actual - system）
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stocktake_id')->references('id')->on('warehouse_stocktakes')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });

        // ── 10. 倉儲異動日誌（所有進出庫的完整記錄）──────────────────
        Schema::create('warehouse_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();
            $table->string('type');                    // receipt / dispatch / stocktake_adjust / manual
            $table->string('source');                  // warehouse / branch_{code}
            $table->string('destination')->nullable(); // warehouse / branch_{code}
            $table->integer('quantity');               // 正數=增加，負數=減少
            $table->integer('before_quantity');        // 異動前數量
            $table->integer('after_quantity');         // 異動後數量
            $table->string('reference_type')->nullable(); // 關聯單據類型
            $table->unsignedBigInteger('reference_id')->nullable(); // 關聯單據 ID
            $table->unsignedBigInteger('user_id');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });

        // ── 11. 使用者與分店關聯（分店人員只能看自己分店）────────────
        Schema::create('user_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('warehouse_movements');
        Schema::dropIfExists('warehouse_stocktake_items');
        Schema::dropIfExists('warehouse_stocktakes');
        Schema::dropIfExists('warehouse_dispatch_items');
        Schema::dropIfExists('warehouse_dispatches');
        Schema::dropIfExists('warehouse_receipt_items');
        Schema::dropIfExists('warehouse_receipts');
        Schema::dropIfExists('branch_stocks');
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('branches');
    }
};
