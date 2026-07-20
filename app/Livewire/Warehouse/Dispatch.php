<?php

namespace App\Livewire\Warehouse;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseDispatch as DispatchModel;
use App\Models\WarehouseStock;
use App\Services\Warehouse\WarehouseService;
use Livewire\Component;
use Livewire\WithPagination;

class Dispatch extends Component
{
    use WithPagination;

    public string $branchId      = '';
    public string $dispatchDate  = '';
    public string $notes         = '';
    public array  $dispatchItems = [];
    public bool   $showForm      = false;
    public string $search        = '';

    protected $queryString = ['search'];

    public function mount(): void
    {
        $this->dispatchDate = now()->format('Y-m-d');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->dispatchItems[] = [
            'product_id'       => '',
            'variant_id'       => '',
            'quantity'         => 1,
            'variants'         => [],
            'available_stock'  => null,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->dispatchItems[$index]);
        $this->dispatchItems = array_values($this->dispatchItems);
        if (empty($this->dispatchItems)) {
            $this->addItem();
        }
    }

    public function updatedDispatchItems($value, $key): void
    {
        $parts = explode('.', $key);
        $index = (int) $parts[0];
        $field = $parts[1] ?? '';

        if ($field === 'product_id') {
            $productId = $value;
            if ($productId) {
                $variants = ProductVariant::where('product_id', $productId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name'])
                    ->toArray();
                $this->dispatchItems[$index]['variants']   = $variants;
                $this->dispatchItems[$index]['variant_id'] = '';

                // 無規格時直接顯示倉庫庫存
                if (empty($variants)) {
                    $stock = WarehouseStock::where('product_id', $productId)
                        ->whereNull('variant_id')
                        ->value('quantity');
                    $this->dispatchItems[$index]['available_stock'] = $stock ?? 0;
                } else {
                    $this->dispatchItems[$index]['available_stock'] = null;
                }
            } else {
                $this->dispatchItems[$index]['variants']        = [];
                $this->dispatchItems[$index]['variant_id']      = '';
                $this->dispatchItems[$index]['available_stock'] = null;
            }
        }

        if ($field === 'variant_id' && $value) {
            $productId = $this->dispatchItems[$index]['product_id'];
            $stock = WarehouseStock::where('product_id', $productId)
                ->where('variant_id', $value)
                ->value('quantity');
            $this->dispatchItems[$index]['available_stock'] = $stock ?? 0;
        }
    }

    public function saveDispatch(): void
    {
        $this->validate([
            'branchId'                  => 'required|exists:branches,id',
            'dispatchDate'              => 'required|date',
            'dispatchItems'             => 'required|array|min:1',
            'dispatchItems.*.product_id'=> 'required|exists:products,id',
            'dispatchItems.*.quantity'  => 'required|integer|min:1',
        ], [
            'branchId.required'                   => '請選擇目標分店',
            'dispatchDate.required'               => '請選擇出庫日期',
            'dispatchItems.*.product_id.required' => '請選擇商品',
            'dispatchItems.*.quantity.min'        => '數量至少為 1',
        ]);

        try {
            $service = app(WarehouseService::class);
            $service->createDispatch(
                [
                    'branch_id'     => $this->branchId,
                    'dispatch_date' => $this->dispatchDate,
                    'notes'         => $this->notes,
                ],
                array_map(fn($item) => [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?: null,
                    'quantity'   => $item['quantity'],
                ], $this->dispatchItems)
            );

            $this->resetForm();
            $this->dispatch('toast', type: 'success', message: '出庫單建立成功！庫存已同步更新。');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function resetForm(): void
    {
        $this->branchId      = '';
        $this->dispatchDate  = now()->format('Y-m-d');
        $this->notes         = '';
        $this->dispatchItems = [];
        $this->showForm      = false;
        $this->addItem();
    }

    public function render()
    {
        $dispatches = DispatchModel::with(['branch', 'creator', 'items'])
            ->when($this->search, fn($q) => $q->where('dispatch_no', 'like', "%{$this->search}%"))
            ->orderByDesc('created_at')
            ->paginate(15);

        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('livewire.warehouse.dispatch', compact('dispatches', 'branches', 'products'));
    }
}
