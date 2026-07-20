<?php

namespace App\Livewire\Warehouse;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Models\WarehouseStocktake as StocktakeModel;
use App\Services\Warehouse\WarehouseService;
use Livewire\Component;
use Livewire\WithPagination;

class Stocktake extends Component
{
    use WithPagination;

    public string $type          = 'warehouse';
    public string $branchId      = '';
    public string $stocktakeDate = '';
    public string $notes         = '';
    public array  $stocktakeItems = [];
    public bool   $showForm      = false;
    public string $search        = '';

    // 確認盤點 modal
    public bool $showConfirmModal = false;
    public ?int $confirmId        = null;

    protected $queryString = ['search'];

    public function mount(): void
    {
        $this->stocktakeDate = now()->format('Y-m-d');
    }

    public function updatedType(): void
    {
        $this->branchId      = '';
        $this->stocktakeItems = [];
        $this->loadAllProducts();
    }

    public function updatedBranchId(): void
    {
        $this->stocktakeItems = [];
        $this->loadAllProducts();
    }

    /**
     * 載入所有商品（含系統庫存）供盤點
     */
    public function loadAllProducts(): void
    {
        $products = Product::with('activeVariants')->orderBy('name')->get();
        $items    = [];

        foreach ($products as $product) {
            if ($product->activeVariants->isNotEmpty()) {
                foreach ($product->activeVariants as $variant) {
                    $systemQty = $this->getSystemQty($product->id, $variant->id);
                    $items[] = [
                        'product_id'      => $product->id,
                        'product_name'    => $product->name,
                        'variant_id'      => $variant->id,
                        'variant_name'    => $variant->name,
                        'system_quantity' => $systemQty,
                        'actual_quantity' => $systemQty, // 預設與系統相同
                        'notes'           => '',
                    ];
                }
            } else {
                $systemQty = $this->getSystemQty($product->id, null);
                $items[] = [
                    'product_id'      => $product->id,
                    'product_name'    => $product->name,
                    'variant_id'      => null,
                    'variant_name'    => null,
                    'system_quantity' => $systemQty,
                    'actual_quantity' => $systemQty,
                    'notes'           => '',
                ];
            }
        }

        $this->stocktakeItems = $items;
    }

    private function getSystemQty(int $productId, ?int $variantId): int
    {
        if ($this->type === 'warehouse') {
            return (int) WarehouseStock::where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->value('quantity') ?? 0;
        }

        if (! $this->branchId) return 0;

        return (int) BranchStock::where('branch_id', $this->branchId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->value('quantity') ?? 0;
    }

    public function saveStocktake(): void
    {
        $this->validate([
            'stocktakeDate' => 'required|date',
            'stocktakeItems'=> 'required|array|min:1',
            'stocktakeItems.*.actual_quantity' => 'required|integer|min:0',
        ], [
            'stocktakeDate.required' => '請選擇盤點日期',
            'stocktakeItems.*.actual_quantity.required' => '請填寫實際數量',
            'stocktakeItems.*.actual_quantity.min'      => '實際數量不能為負數',
        ]);

        if ($this->type === 'branch' && ! $this->branchId) {
            $this->addError('branchId', '請選擇盤點分店');
            return;
        }

        try {
            $service = app(WarehouseService::class);
            $service->createStocktake(
                [
                    'type'           => $this->type,
                    'branch_id'      => $this->branchId ?: null,
                    'stocktake_date' => $this->stocktakeDate,
                    'notes'          => $this->notes,
                ],
                array_map(fn($item) => [
                    'product_id'      => $item['product_id'],
                    'variant_id'      => $item['variant_id'],
                    'actual_quantity' => $item['actual_quantity'],
                    'notes'           => $item['notes'] ?? null,
                ], $this->stocktakeItems)
            );

            $this->resetForm();
            $this->dispatch('toast', type: 'success', message: '盤點單已建立（草稿），請確認後套用差異。');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openConfirm(int $id): void
    {
        $this->confirmId        = $id;
        $this->showConfirmModal = true;
    }

    public function confirmStocktake(): void
    {
        try {
            $stocktake = StocktakeModel::with('items')->findOrFail($this->confirmId);
            $service   = app(WarehouseService::class);
            $service->confirmStocktake($stocktake);

            $this->showConfirmModal = false;
            $this->confirmId        = null;
            $this->dispatch('toast', type: 'success', message: '盤點已確認，庫存差異已套用！');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function resetForm(): void
    {
        $this->type           = 'warehouse';
        $this->branchId       = '';
        $this->stocktakeDate  = now()->format('Y-m-d');
        $this->notes          = '';
        $this->stocktakeItems = [];
        $this->showForm       = false;
    }

    public function render()
    {
        $stocktakes = StocktakeModel::with(['branch', 'creator', 'items'])
            ->when($this->search, fn($q) => $q->where('stocktake_no', 'like', "%{$this->search}%"))
            ->orderByDesc('created_at')
            ->paginate(15);

        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('livewire.warehouse.stocktake', compact('stocktakes', 'branches'));
    }
}
