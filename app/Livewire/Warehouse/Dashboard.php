<?php

namespace App\Livewire\Warehouse;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseMovement;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseStock;
use App\Models\WarehouseStocktake;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        // 倉庫庫存統計
        $totalWarehouseItems  = WarehouseStock::where('quantity', '>', 0)->count();
        $lowStockCount        = WarehouseStock::whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('quantity', '>', 0)->count();
        $outOfStockCount      = WarehouseStock::where('quantity', '<=', 0)->count();

        // 本月入庫 / 出庫次數
        $monthlyReceipts  = WarehouseReceipt::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $monthlyDispatches = WarehouseDispatch::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        // 待確認盤點單
        $pendingStocktakes = WarehouseStocktake::where('status', 'draft')->count();

        // 低庫存商品（最多 8 筆）
        $lowStockItems = WarehouseStock::with(['product', 'variant'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->limit(8)
            ->get();

        // 缺貨商品（最多 8 筆）
        $outOfStockItems = WarehouseStock::with(['product', 'variant'])
            ->where('quantity', '<=', 0)
            ->limit(8)
            ->get();

        // 各分店庫存概覽
        $branches = Branch::where('is_active', true)->get();
        $branchStockSummary = [];
        foreach ($branches as $branch) {
            $branchStockSummary[] = [
                'branch'    => $branch,
                'total_qty' => BranchStock::where('branch_id', $branch->id)->sum('quantity'),
                'items'     => BranchStock::where('branch_id', $branch->id)->where('quantity', '>', 0)->count(),
            ];
        }

        // 最近 10 筆異動記錄
        $recentMovements = WarehouseMovement::with(['product', 'variant', 'user'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.warehouse.dashboard', compact(
            'totalWarehouseItems',
            'lowStockCount',
            'outOfStockCount',
            'monthlyReceipts',
            'monthlyDispatches',
            'pendingStocktakes',
            'lowStockItems',
            'outOfStockItems',
            'branchStockSummary',
            'recentMovements',
        ));
    }
}
