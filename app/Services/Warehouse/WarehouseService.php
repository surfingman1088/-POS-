<?php

namespace App\Services\Warehouse;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseMovement;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseReceiptItem;
use App\Models\WarehouseStock;
use App\Models\WarehouseStocktake;
use App\Models\WarehouseStocktakeItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    // ────────────────────────────────────────────────────────────
    //  入庫（廠商進貨）
    // ────────────────────────────────────────────────────────────

    /**
     * 建立入庫單並更新倉庫庫存
     *
     * @param array $header  入庫單表頭資料
     * @param array $items   入庫明細 [['product_id', 'variant_id', 'quantity', 'unit_cost'], ...]
     */
    public function createReceipt(array $header, array $items): WarehouseReceipt
    {
        return DB::transaction(function () use ($header, $items) {
            // 建立入庫單
            $receipt = WarehouseReceipt::create([
                'receipt_no'       => WarehouseReceipt::generateReceiptNo(),
                'supplier_name'    => $header['supplier_name'] ?? null,
                'supplier_contact' => $header['supplier_contact'] ?? null,
                'receipt_date'     => $header['receipt_date'],
                'batch_no'         => $header['batch_no'] ?? null,
                'notes'            => $header['notes'] ?? null,
                'status'           => 'completed',
                'created_by'       => Auth::id(),
            ]);

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $variantId = $item['variant_id'] ?? null;
                $qty       = (int) $item['quantity'];

                // 建立明細
                WarehouseReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity'   => $qty,
                    'unit_cost'  => $item['unit_cost'] ?? null,
                ]);

                // 更新倉庫庫存
                $stock = WarehouseStock::firstOrCreate(
                    ['product_id' => $productId, 'variant_id' => $variantId],
                    ['quantity' => 0, 'low_stock_threshold' => 10]
                );

                $before = $stock->quantity;
                $stock->increment('quantity', $qty);

                // 記錄異動日誌
                $this->logMovement([
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'type'            => 'receipt',
                    'source'          => 'supplier',
                    'destination'     => 'warehouse',
                    'quantity'        => $qty,
                    'before_quantity' => $before,
                    'after_quantity'  => $before + $qty,
                    'reference_type'  => 'warehouse_receipt',
                    'reference_id'    => $receipt->id,
                    'notes'           => "入庫單 {$receipt->receipt_no}",
                ]);
            }

            return $receipt;
        });
    }

    // ────────────────────────────────────────────────────────────
    //  出庫（撥貨到分店）
    // ────────────────────────────────────────────────────────────

    /**
     * 建立出庫單並更新倉庫庫存及分店庫存
     */
    public function createDispatch(array $header, array $items): WarehouseDispatch
    {
        return DB::transaction(function () use ($header, $items) {
            $branch = Branch::findOrFail($header['branch_id']);

            $dispatch = WarehouseDispatch::create([
                'dispatch_no'   => WarehouseDispatch::generateDispatchNo(),
                'branch_id'     => $branch->id,
                'dispatch_date' => $header['dispatch_date'],
                'notes'         => $header['notes'] ?? null,
                'status'        => 'completed',
                'created_by'    => Auth::id(),
            ]);

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $variantId = $item['variant_id'] ?? null;
                $qty       = (int) $item['quantity'];

                // 檢查倉庫庫存是否足夠
                $warehouseStock = WarehouseStock::where('product_id', $productId)
                    ->where('variant_id', $variantId)
                    ->first();

                if (! $warehouseStock || $warehouseStock->quantity < $qty) {
                    $product = Product::find($productId);
                    throw new \Exception("商品「{$product->name}」倉庫庫存不足（現有：" . ($warehouseStock?->quantity ?? 0) . "，需要：{$qty}）");
                }

                // 建立明細
                WarehouseDispatchItem::create([
                    'dispatch_id' => $dispatch->id,
                    'product_id'  => $productId,
                    'variant_id'  => $variantId,
                    'quantity'    => $qty,
                ]);

                // 扣減倉庫庫存
                $beforeWarehouse = $warehouseStock->quantity;
                $warehouseStock->decrement('quantity', $qty);

                $this->logMovement([
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'type'            => 'dispatch',
                    'source'          => 'warehouse',
                    'destination'     => "branch_{$branch->code}",
                    'quantity'        => -$qty,
                    'before_quantity' => $beforeWarehouse,
                    'after_quantity'  => $beforeWarehouse - $qty,
                    'reference_type'  => 'warehouse_dispatch',
                    'reference_id'    => $dispatch->id,
                    'notes'           => "出庫單 {$dispatch->dispatch_no} → {$branch->name}",
                ]);

                // 增加分店庫存
                $branchStock = BranchStock::firstOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $productId, 'variant_id' => $variantId],
                    ['quantity' => 0]
                );
                $beforeBranch = $branchStock->quantity;
                $branchStock->increment('quantity', $qty);

                $this->logMovement([
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'type'            => 'dispatch',
                    'source'          => 'warehouse',
                    'destination'     => "branch_{$branch->code}",
                    'quantity'        => $qty,
                    'before_quantity' => $beforeBranch,
                    'after_quantity'  => $beforeBranch + $qty,
                    'reference_type'  => 'warehouse_dispatch',
                    'reference_id'    => $dispatch->id,
                    'notes'           => "撥貨到{$branch->name}，出庫單 {$dispatch->dispatch_no}",
                ]);

                // 同步更新 POS products 表的庫存（直連同一資料庫）
                $this->syncPosProductStock($productId, $variantId);
            }

            return $dispatch;
        });
    }

    // ────────────────────────────────────────────────────────────
    //  盤點
    // ────────────────────────────────────────────────────────────

    /**
     * 建立盤點單（草稿）
     */
    public function createStocktake(array $header, array $items): WarehouseStocktake
    {
        return DB::transaction(function () use ($header, $items) {
            $stocktake = WarehouseStocktake::create([
                'stocktake_no'   => WarehouseStocktake::generateStocktakeNo(),
                'type'           => $header['type'] ?? 'warehouse',
                'branch_id'      => $header['branch_id'] ?? null,
                'stocktake_date' => $header['stocktake_date'],
                'notes'          => $header['notes'] ?? null,
                'status'         => 'draft',
                'created_by'     => Auth::id(),
            ]);

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $variantId = $item['variant_id'] ?? null;
                $actual    = (int) $item['actual_quantity'];

                // 取得系統庫存
                if ($stocktake->type === 'warehouse') {
                    $systemQty = WarehouseStock::where('product_id', $productId)
                        ->where('variant_id', $variantId)
                        ->value('quantity') ?? 0;
                } else {
                    $systemQty = BranchStock::where('branch_id', $stocktake->branch_id)
                        ->where('product_id', $productId)
                        ->where('variant_id', $variantId)
                        ->value('quantity') ?? 0;
                }

                WarehouseStocktakeItem::create([
                    'stocktake_id'    => $stocktake->id,
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'system_quantity' => $systemQty,
                    'actual_quantity' => $actual,
                    'difference'      => $actual - $systemQty,
                    'notes'           => $item['notes'] ?? null,
                ]);
            }

            return $stocktake;
        });
    }

    /**
     * 確認盤點單並套用差異
     */
    public function confirmStocktake(WarehouseStocktake $stocktake): void
    {
        DB::transaction(function () use ($stocktake) {
            foreach ($stocktake->items as $item) {
                if ($item->difference === 0) continue;

                if ($stocktake->type === 'warehouse') {
                    $stock = WarehouseStock::firstOrCreate(
                        ['product_id' => $item->product_id, 'variant_id' => $item->variant_id],
                        ['quantity' => 0, 'low_stock_threshold' => 10]
                    );
                    $before = $stock->quantity;
                    $stock->update(['quantity' => $item->actual_quantity]);
                } else {
                    $stock = BranchStock::firstOrCreate(
                        ['branch_id' => $stocktake->branch_id, 'product_id' => $item->product_id, 'variant_id' => $item->variant_id],
                        ['quantity' => 0]
                    );
                    $before = $stock->quantity;
                    $stock->update(['quantity' => $item->actual_quantity]);
                }

                $this->logMovement([
                    'product_id'      => $item->product_id,
                    'variant_id'      => $item->variant_id,
                    'type'            => 'stocktake_adjust',
                    'source'          => $stocktake->type === 'warehouse' ? 'warehouse' : "branch_{$stocktake->branch?->code}",
                    'destination'     => null,
                    'quantity'        => $item->difference,
                    'before_quantity' => $before,
                    'after_quantity'  => $item->actual_quantity,
                    'reference_type'  => 'warehouse_stocktake',
                    'reference_id'    => $stocktake->id,
                    'notes'           => "盤點調整 {$stocktake->stocktake_no}",
                ]);

                // 同步 POS 庫存
                $this->syncPosProductStock($item->product_id, $item->variant_id);
            }

            $stocktake->update([
                'status'       => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);
        });
    }

    // ────────────────────────────────────────────────────────────
    //  同步 POS products 庫存
    // ────────────────────────────────────────────────────────────

    /**
     * 將所有分店庫存加總後同步回 POS products.stocks 欄位
     * 讓 POS 訂單頁面能即時反映正確庫存
     */
    public function syncPosProductStock(int $productId, ?int $variantId = null): void
    {
        if ($variantId) {
            // 有規格：更新 product_variants.stocks
            $totalBranchStock = BranchStock::where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->sum('quantity');

            \App\Models\ProductVariant::where('id', $variantId)
                ->update(['stocks' => $totalBranchStock]);

            // 同步主商品庫存為所有規格的加總
            $totalVariantStock = \App\Models\ProductVariant::where('product_id', $productId)
                ->where('is_active', true)
                ->sum('stocks');

            Product::where('id', $productId)->update(['stocks' => $totalVariantStock]);
        } else {
            // 無規格：直接加總所有分店庫存
            $totalBranchStock = BranchStock::where('product_id', $productId)
                ->whereNull('variant_id')
                ->sum('quantity');

            Product::where('id', $productId)->update(['stocks' => $totalBranchStock]);
        }
    }

    // ────────────────────────────────────────────────────────────
    //  低庫存警示
    // ────────────────────────────────────────────────────────────

    /**
     * 取得倉庫低庫存商品清單
     */
    public function getLowStockItems(): \Illuminate\Database\Eloquent\Collection
    {
        return WarehouseStock::with(['product', 'variant'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->get();
    }

    /**
     * 取得倉庫缺貨商品清單
     */
    public function getOutOfStockItems(): \Illuminate\Database\Eloquent\Collection
    {
        return WarehouseStock::with(['product', 'variant'])
            ->where('quantity', '<=', 0)
            ->get();
    }

    // ────────────────────────────────────────────────────────────
    //  私有輔助方法
    // ────────────────────────────────────────────────────────────

    private function logMovement(array $data): void
    {
        WarehouseMovement::create(array_merge($data, [
            'user_id' => Auth::id(),
        ]));
    }
}
