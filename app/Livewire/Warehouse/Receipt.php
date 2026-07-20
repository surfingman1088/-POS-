<?php

namespace App\Livewire\Warehouse;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseReceipt as ReceiptModel;
use App\Services\Warehouse\WarehouseService;
use Livewire\Component;
use Livewire\WithPagination;

class Receipt extends Component
{
    use WithPagination;

    // 表單欄位
    public string  $supplierName    = '';
    public string  $supplierContact = '';
    public string  $receiptDate     = '';
    public string  $batchNo         = '';
    public string  $notes           = '';

    // 入庫明細（動態行）
    public array $receiptItems = [];

    // UI 狀態
    public bool   $showForm    = false;
    public string $search      = '';

    protected $queryString = ['search'];

    public function mount(): void
    {
        $this->receiptDate = now()->format('Y-m-d');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->receiptItems[] = [
            'product_id' => '',
            'variant_id' => '',
            'quantity'   => 1,
            'unit_cost'  => '',
            'variants'   => [],
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->receiptItems[$index]);
        $this->receiptItems = array_values($this->receiptItems);
        if (empty($this->receiptItems)) {
            $this->addItem();
        }
    }

    public function updatedReceiptItems($value, $key): void
    {
        // 當選擇商品時，載入該商品的規格選項
        if (str_ends_with($key, '.product_id')) {
            $index = (int) explode('.', $key)[0];
            $productId = $value;
            if ($productId) {
                $variants = ProductVariant::where('product_id', $productId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name'])
                    ->toArray();
                $this->receiptItems[$index]['variants']   = $variants;
                $this->receiptItems[$index]['variant_id'] = '';
            } else {
                $this->receiptItems[$index]['variants']   = [];
                $this->receiptItems[$index]['variant_id'] = '';
            }
        }
    }

    public function saveReceipt(): void
    {
        $this->validate([
            'receiptDate'              => 'required|date',
            'receiptItems'             => 'required|array|min:1',
            'receiptItems.*.product_id'=> 'required|exists:products,id',
            'receiptItems.*.quantity'  => 'required|integer|min:1',
            'receiptItems.*.unit_cost' => 'nullable|numeric|min:0',
        ], [
            'receiptDate.required'                => '請選擇入庫日期',
            'receiptItems.*.product_id.required'  => '請選擇商品',
            'receiptItems.*.quantity.required'    => '請填寫數量',
            'receiptItems.*.quantity.min'         => '數量至少為 1',
        ]);

        try {
            $service = app(WarehouseService::class);
            $service->createReceipt(
                [
                    'supplier_name'    => $this->supplierName,
                    'supplier_contact' => $this->supplierContact,
                    'receipt_date'     => $this->receiptDate,
                    'batch_no'         => $this->batchNo,
                    'notes'            => $this->notes,
                ],
                array_map(fn($item) => [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?: null,
                    'quantity'   => $item['quantity'],
                    'unit_cost'  => $item['unit_cost'] ?: null,
                ], $this->receiptItems)
            );

            $this->resetForm();
            $this->dispatch('toast', type: 'success', message: '入庫單建立成功！');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function resetForm(): void
    {
        $this->supplierName    = '';
        $this->supplierContact = '';
        $this->receiptDate     = now()->format('Y-m-d');
        $this->batchNo         = '';
        $this->notes           = '';
        $this->receiptItems    = [];
        $this->showForm        = false;
        $this->addItem();
    }

    public function render()
    {
        $receipts = ReceiptModel::with(['creator', 'items'])
            ->when($this->search, fn($q) => $q->where('receipt_no', 'like', "%{$this->search}%")
                ->orWhere('supplier_name', 'like', "%{$this->search}%"))
            ->orderByDesc('created_at')
            ->paginate(15);

        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('livewire.warehouse.receipt', compact('receipts', 'products'));
    }
}
