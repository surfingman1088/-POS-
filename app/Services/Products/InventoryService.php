<?php

namespace App\Services\Products;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    // =========================================================
    // 商品主庫存（無規格）
    // =========================================================

    public function deduct(
        int $productId,
        int $qty,
        string $type = 'manual_adjustment',
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product' => 'Product not found.',
            ]);
        }

        if ($product->stocks < $qty) {
            throw ValidationException::withMessages([
                'product' => "Insufficient stock for {$product->name}",
            ]);
        }

        $beforeStocks = (int) $product->stocks;
        $beforeSold   = (int) ($product->sold ?? 0);

        $product->stocks -= $qty;
        $product->sold   += $qty;
        $product->is_in_stock = $product->stocks > 0;
        $product->save();

        $this->logMovement(
            $product, $type, $qty,
            $beforeStocks, $beforeSold,
            (int) $product->stocks, (int) $product->sold,
            $userId, $reference, $remarks
        );
    }

    public function restore(
        int $productId,
        int $qty,
        string $type = 'manual_adjustment',
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($productId);

        if (! $product) {
            return;
        }

        $beforeStocks = (int) $product->stocks;
        $beforeSold   = (int) ($product->sold ?? 0);

        $product->stocks += $qty;
        $product->sold    = max(0, $product->sold - $qty);
        $product->is_in_stock = true;
        $product->save();

        $this->logMovement(
            $product, $type, $qty,
            $beforeStocks, $beforeSold,
            (int) $product->stocks, (int) $product->sold,
            $userId, $reference, $remarks
        );
    }

    public function sync(
        int $productId,
        int $oldQty,
        int $newQty,
        string $type = 'manual_adjustment',
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        $difference = $newQty - $oldQty;

        if ($difference > 0) {
            $this->deduct($productId, $difference, $type, $reference, $remarks, $userId);
        }

        if ($difference < 0) {
            $this->restore($productId, abs($difference), $type, $reference, $remarks, $userId);
        }
    }

    // =========================================================
    // 商品規格庫存（有 variant）
    // =========================================================

    /**
     * 扣除規格庫存
     */
    public function deductVariant(
        int $variantId,
        int $qty,
        string $type = 'manual_adjustment',
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        $variant = ProductVariant::query()->lockForUpdate()->with('product')->find($variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'product' => '找不到商品規格。',
            ]);
        }

        if ($variant->stocks < $qty) {
            throw ValidationException::withMessages([
                'product' => "「{$variant->product->name} - {$variant->name}」庫存不足（剩餘 {$variant->stocks}）",
            ]);
        }

        $beforeStocks = (int) $variant->stocks;
        $beforeSold   = (int) ($variant->sold ?? 0);

        $variant->stocks -= $qty;
        $variant->sold   += $qty;
        $variant->save();

        // 同步更新商品主庫存（加總所有規格）
        $this->syncProductStocksFromVariants($variant->product);

        $this->logMovement(
            $variant->product,
            $type,
            $qty,
            $beforeStocks,
            $beforeSold,
            (int) $variant->stocks,
            (int) $variant->sold,
            $userId,
            $reference,
            $remarks . " [規格: {$variant->name}]"
        );
    }

    /**
     * 歸還規格庫存（退款/取消）
     */
    public function restoreVariant(
        int $variantId,
        int $qty,
        string $type = 'manual_adjustment',
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        $variant = ProductVariant::query()->lockForUpdate()->with('product')->find($variantId);

        if (! $variant) {
            return;
        }

        $beforeStocks = (int) $variant->stocks;
        $beforeSold   = (int) ($variant->sold ?? 0);

        $variant->stocks += $qty;
        $variant->sold    = max(0, $variant->sold - $qty);
        $variant->save();

        $this->syncProductStocksFromVariants($variant->product);

        $this->logMovement(
            $variant->product,
            $type,
            $qty,
            $beforeStocks,
            $beforeSold,
            (int) $variant->stocks,
            (int) $variant->sold,
            $userId,
            $reference,
            $remarks . " [規格: {$variant->name}]"
        );
    }

    /**
     * 規格庫存入庫（Staff Restock）
     */
    public function restockVariant(
        int $variantId,
        int $qty,
        ?Model $reference = null,
        ?string $remarks = null,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        $variant = ProductVariant::query()->lockForUpdate()->with('product')->find($variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'product' => '找不到商品規格。',
            ]);
        }

        $beforeStocks = (int) $variant->stocks;
        $beforeSold   = (int) ($variant->sold ?? 0);

        $variant->stocks += $qty;
        $variant->save();

        $this->syncProductStocksFromVariants($variant->product);

        $this->logMovement(
            $variant->product,
            'restock',
            $qty,
            $beforeStocks,
            $beforeSold,
            (int) $variant->stocks,
            (int) $variant->sold,
            $userId,
            $reference,
            $remarks . " [規格: {$variant->name}]"
        );
    }

    /**
     * 將商品主庫存同步為所有規格庫存的加總
     */
    public function syncProductStocksFromVariants(Product $product): void
    {
        $totalStocks = (int) $product->variants()->where('is_active', true)->sum('stocks');
        $totalSold   = (int) $product->variants()->where('is_active', true)->sum('sold');

        $product->stocks      = $totalStocks;
        $product->sold        = $totalSold;
        $product->is_in_stock = $totalStocks > 0;
        $product->save();
    }

    // =========================================================
    // 私有輔助方法
    // =========================================================

    private function logMovement(
        Product $product,
        string $type,
        int $quantity,
        int $beforeStocks,
        int $beforeSold,
        int $afterStocks,
        int $afterSold,
        ?int $userId = null,
        ?Model $reference = null,
        ?string $remarks = null
    ): void {
        InventoryMovement::create([
            'product_id'     => $product->id,
            'user_id'        => $userId ?? Auth::id(),
            'type'           => $type,
            'quantity'       => $quantity,
            'before_stocks'  => $beforeStocks,
            'before_sold'    => $beforeSold,
            'after_stocks'   => $afterStocks,
            'after_sold'     => $afterSold,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id'   => $reference?->getKey(),
            'remarks'        => $remarks,
        ]);
    }
}
