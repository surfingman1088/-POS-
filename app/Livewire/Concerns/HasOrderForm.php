<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;

/**
 * HasOrderForm
 *
 * Drop this trait into Add, Create, and Edit to share:
 *  - customer/employee/product search + selection
 *  - filteredX computed properties
 *  - order-item CRUD + total calculation
 *  - product inline-creation helpers
 *
 * The host component must declare the properties that this trait
 * references (see "Required component properties" below).
 */
trait HasOrderForm
{
    // ──────────────────────────────────────────────────────────────
    // Customer helpers
    // ──────────────────────────────────────────────────────────────

    public function getFilteredCustomersProperty()
    {
        $q    = Customer::query();
        $term = trim($this->customerSearch ?? '');

        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $sub->where('name',           'like', "%{$term}%")
                    ->orWhere('unit',          'like', "%{$term}%")
                    ->orWhere('address',       'like', "%{$term}%")
                    ->orWhere('contact_number','like', "%{$term}%");
            });
        }

        return $q->orderBy('name', 'asc')->take(30)->get();
    }

    public function getSelectedCustomerProperty()
    {
        return $this->selectedCustomerId
            ? Customer::query()->whereKey($this->selectedCustomerId)->first()
            : null;
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->first();
        if (! $customer) {
            return;
        }

        $this->selectedCustomerId    = $customer->id;
        $this->customerName          = $customer->name           ?? '';
        $this->customerUnit          = $customer->unit           ?? '';
        $this->customerAddress       = $customer->address        ?? '';
        $this->customerContact       = $customer->contact_number ?? '';
        $this->isCreatingNewCustomer = false;
        $this->customerSearch        = '';

        $this->resetErrorBag([
            'selectedCustomerId',
            'customerName',
            'customerUnit',
            'customerAddress',
            'customerContact',
        ]);
    }

    public function createNewCustomer(): void
    {
        $this->isCreatingNewCustomer = true;
        $this->selectedCustomerId    = null;
        $this->customerName          = '';
        $this->customerUnit          = '';
        $this->customerAddress       = '';
        $this->customerContact       = '';

        $this->resetErrorBag([
            'selectedCustomerId',
            'customerName',
            'customerUnit',
            'customerAddress',
            'customerContact',
        ]);
    }

    public function cancelNewCustomer(): void
    {
        $this->isCreatingNewCustomer = false;
        $this->customerName          = '';
        $this->customerUnit          = '';
        $this->customerAddress       = '';
        $this->customerContact       = '';

        $this->resetErrorBag([
            'customerName',
            'customerUnit',
            'customerAddress',
            'customerContact',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Employee helpers
    // ──────────────────────────────────────────────────────────────

    public function getFilteredEmployeesProperty()
    {
        $q = Employee::query()
            ->where('status',      'active')
            ->where('is_archived', false);

        $term = trim($this->employeeSearch ?? '');
        if ($term !== '') {
            $q->where('name', 'like', "%{$term}%");
        }

        return $q->orderBy('name', 'asc')->take(30)->get();
    }

    /**
     * Returns the currently-selected employee model.
     * Works for both Add/Create ($selectedEmployeeId) and Edit ($delivered_by).
     */
    public function getSelectedEmployeeProperty()
    {
        $id = $this->selectedEmployeeId ?? $this->delivered_by ?? null;

        return $id ? Employee::query()->whereKey($id)->first() : null;
    }

    public function selectEmployee(int $employeeId): void
    {
        $employee = Employee::query()
            ->where('status',      'active')
            ->where('is_archived', false)
            ->whereKey($employeeId)
            ->first();

        if (! $employee) {
            return;
        }

        if (property_exists($this, 'selectedEmployeeId')) {
            $this->selectedEmployeeId = $employee->id;
        }
        if (property_exists($this, 'delivered_by')) {
            $this->delivered_by = $employee->id;
        }

        $this->employeeSearch = '';
        $this->resetErrorBag(['selectedEmployeeId', 'delivered_by']);
    }

    /** Force-assign even if the employee is currently in-transit. */
    public function forceSelectEmployee(int $employeeId): void
    {
        $this->selectEmployee($employeeId);
    }

    public function isEmployeeInTransit(int $employeeId): bool
    {
        return Order::query()
            ->where('delivered_by', $employeeId)
            ->where('status',       'in_transit')
            ->exists();
    }

    // ──────────────────────────────────────────────────────────────
    // Product helpers
    // ──────────────────────────────────────────────────────────────

    public function getFilteredProductsProperty()
    {
        $q = Product::query()
            ->where('is_in_stock', true)
            ->where('stocks',      '>', 0);

        $term = trim($this->productSearch ?? '');
        if ($term !== '') {
            $q->where(function ($sub) use ($term) {
                $sub->where('name',         'like', "%{$term}%")
                    ->orWhere('description','like', "%{$term}%")
                    ->orWhereHas('categoryRecord', fn ($cq) => $cq->where('name', 'like', "%{$term}%"));
            });
        }

        return $q->orderBy('name', 'asc')->take(50)->get();
    }

    public function selectProduct(int $productId, int $itemIndex): void
    {
        $product = Product::query()
            ->where('id',          $productId)
            ->where('is_in_stock', true)
            ->where('stocks',      '>', 0)
            ->first();

        if (! $product || ! isset($this->orderItems[$itemIndex])) {
            return;
        }

        $this->orderItems[$itemIndex]['product_id']     = $product->id;
        $this->orderItems[$itemIndex]['product_name']   = $product->name;
        $this->orderItems[$itemIndex]['stocks']         = (int)   $product->stocks;
        $this->orderItems[$itemIndex]['price']          = (float) $product->price;
        $this->orderItems[$itemIndex]['original_price'] = (float) $product->price;
        $this->orderItems[$itemIndex]['discount']       = (float) ($this->orderItems[$itemIndex]['discount'] ?? 0);

        // Clamp qty to available stock
        $currentQty = (int) ($this->orderItems[$itemIndex]['quantity'] ?? 1);
        $this->orderItems[$itemIndex]['quantity'] = min(max($currentQty, 1), (int) $product->stocks);

        $this->calculateItemTotal($itemIndex);

        $this->productSearch = '';
    }

    /**
     * POS-style add: called when the user clicks a product card in the grid.
     *
     * • Already in cart  → increments qty by 1 (capped at stock level)
     * • Blank slot exists → fills it via selectProduct()
     * • No blank slot     → appends a new blank row then fills it
     *
     * Reuses addOrderItem() and selectProduct() so all business logic
     * (stock clamping, total calculation, etc.) stays in one place.
     */
    public function addProductToCart(int $productId): void
    {
        // ── Already in cart? Increment qty ──────────────────────────
        foreach ($this->orderItems as $index => $item) {
            if ((int) ($item['product_id'] ?? 0) === $productId) {
                $currentQty = (int) ($item['quantity'] ?? 1);
                $maxStock   = (int) ($item['stocks']   ?? 0);

                // Only increment if stock allows
                if ($maxStock > 0 && $currentQty < $maxStock) {
                    $this->orderItems[$index]['quantity'] = $currentQty + 1;
                    $this->calculateItemTotal($index);
                }

                return; // Either incremented or already at max — either way, done.
            }
        }

        // ── Find any blank slot (no product selected yet) ────────────
        $targetIndex = null;
        foreach ($this->orderItems as $index => $item) {
            if (empty($item['product_id'])) {
                $targetIndex = $index;
                break;
            }
        }

        // ── No blank slot → append a fresh row ───────────────────────
        if ($targetIndex === null) {
            $this->addOrderItem();
            $targetIndex = array_key_last($this->orderItems);
        }

        // ── Fill the slot (selectProduct handles stock check + totals) ─
        $this->selectProduct($productId, $targetIndex);
    }

    // ──────────────────────────────────────────────────────────────
    // Order-item management
    // ──────────────────────────────────────────────────────────────

    public function addOrderItem(): void
    {
        $this->orderItems[] = [
            'id'             => null,
            'product_id'     => null,
            'product_name'   => '',
            'stocks'         => 0,
            'quantity'       => 1,
            'price'          => 0,
            'original_price' => 0,
            'discount'       => 0,
            'is_free'        => false,
            'total'          => 0,
        ];
    }

    public function removeOrderItem(int $index): void
    {
        if (! isset($this->orderItems[$index])) {
            return;
        }

        unset($this->orderItems[$index]);
        $this->orderItems = array_values($this->orderItems);

        // Keep at least one blank row so the cart never looks broken
        if (empty($this->orderItems)) {
            $this->addOrderItem();
        }
    }

    /**
     * Central total calculator.
     * Call this any time quantity, price, discount, or is_free changes.
     */
    public function calculateItemTotal(int $index): void
    {
        if (! isset($this->orderItems[$index])) {
            return;
        }

        $isFree   = (bool)  ($this->orderItems[$index]['is_free']  ?? false);
        $discount = max(0, (float) ($this->orderItems[$index]['discount'] ?? 0));

        if ($isFree) {
            $this->orderItems[$index]['discount'] = 0;
            $this->orderItems[$index]['total']    = 0;
            return;
        }

        $qtyRaw   = $this->orderItems[$index]['quantity'] ?? null;
        $qty      = (is_numeric($qtyRaw) && $qtyRaw !== '') ? max(1, (int) $qtyRaw) : 0;
        $price    = max(0, (float) ($this->orderItems[$index]['price'] ?? 0));
        $subtotal = $qty * $price;
        $discount = min($discount, $subtotal);

        $this->orderItems[$index]['discount'] = $discount;
        $this->orderItems[$index]['total']    = max(0, $subtotal - $discount);
    }

    /**
     * Handles Livewire's updatedOrderItems lifecycle hook.
     * Call from your component's updatedOrderItems() method.
     */
    public function handleUpdatedOrderItem(mixed $value, ?string $key): void
    {
        // Checks to prevent "undefined index" errors when Livewire
        // triggers updatedOrderItems without a key (e.g. after adding a new item).
        if (! $key) {
            return;
        }

        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);
        $index = (int) $index;

        if (! isset($this->orderItems[$index]) || ! $field) {
            return;
        }

        match ($field) {
            'product_id' => $this->onProductIdChange($index),
            'quantity'   => $this->onQuantityChange($index),
            'price'      => $this->onPriceChange($index),
            'discount'   => $this->onDiscountChange($index),
            'is_free'    => $this->calculateItemTotal($index),
            default      => null,
        };
    }

    private function onProductIdChange(int $index): void
    {
        $productId = (int) ($this->orderItems[$index]['product_id'] ?? 0);
        $product   = $productId ? Product::query()->whereKey($productId)->first() : null;

        if ($product && $product->is_in_stock && $product->stocks > 0) {
            $this->orderItems[$index]['product_name']   = $product->name;
            $this->orderItems[$index]['stocks']         = (int)   $product->stocks;
            $this->orderItems[$index]['price']          = (float) $product->price;
            $this->orderItems[$index]['original_price'] = (float) $product->price;

            $qty = (int) ($this->orderItems[$index]['quantity'] ?? 1);
            $this->orderItems[$index]['quantity'] = min(max($qty, 1), (int) $product->stocks);
        } else {
            $this->orderItems[$index]['product_id']   = null;
            $this->orderItems[$index]['product_name'] = '';
            $this->orderItems[$index]['stocks']       = 0;
            $this->orderItems[$index]['price']        = 0;
            $this->orderItems[$index]['total']        = 0;

            if ($productId) {
                $this->addError("orderItems.{$index}.product_id", __('Product is out of stock.'));
            }
            return;
        }

        $this->calculateItemTotal($index);
    }

    private function onQuantityChange(int $index): void
    {
        $raw = $this->orderItems[$index]['quantity'] ?? null;

        if ($raw === '' || $raw === null) {
            return;
        }

        $qty       = max(1, (int) $raw);
        $productId = $this->orderItems[$index]['product_id'] ?? null;

        if ($productId) {
            $product = Product::query()->whereKey($productId)->first();
            if ($product) {
                $qty = min($qty, max((int) $product->stocks, 1));
            }
        }

        $this->orderItems[$index]['quantity'] = $qty;
        $this->calculateItemTotal($index);
    }

    private function onPriceChange(int $index): void
    {
        $this->orderItems[$index]['price']          = max(0, (float) ($this->orderItems[$index]['price'] ?? 0));
        $this->orderItems[$index]['original_price'] = $this->orderItems[$index]['price'];
        $this->calculateItemTotal($index);
    }

    private function onDiscountChange(int $index): void
    {
        $this->orderItems[$index]['discount'] = max(0, (float) ($this->orderItems[$index]['discount'] ?? 0));
        $this->calculateItemTotal($index);
    }

    // ──────────────────────────────────────────────────────────────
    // Order total (computed property)
    // ──────────────────────────────────────────────────────────────

    public function getTotalAmountProperty(): float
    {
        return (float) collect($this->orderItems)->sum(function ($item) {
            if ($item['is_free'] ?? false) {
                return 0;
            }
            $qty = (is_numeric($item['quantity'] ?? null) && ($item['quantity'] !== ''))
                ? max(1, (int) $item['quantity'])
                : 0;
            $subtotal = $qty * max(0, (float) ($item['price'] ?? 0));
            $discount = min(max(0, (float) ($item['discount'] ?? 0)), $subtotal);
            return max(0, $subtotal - $discount);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Inline product-form helpers
    // ──────────────────────────────────────────────────────────────

    public function openProductForm(?int $itemIndex = null): void
    {
        $this->showProductForm    = true;
        $this->productTargetIndex = $itemIndex;
        $this->resetProductForm();
    }

    public function closeProductForm(): void
    {
        $this->showProductForm    = false;
        $this->productTargetIndex = null;
        $this->resetProductForm();
        $this->resetErrorBag([
            'productName',
            'productDescription',
            'productCategory',
            'productStocks',
            'productPrice',
        ]);
    }

    public function resetProductForm(): void
    {
        $this->productName        = '';
        $this->productDescription = '';
        $this->productCategory    = '';
        $this->productStocks      = 1;
        $this->productPrice       = 0;
    }

    public function createProduct(): void
    {
        $this->validate([
            'productName'        => 'required|string|max:255',
            'productDescription' => 'nullable|string',
            'productCategory'    => 'required|integer|exists:product_categories,id',
            'productStocks'      => 'required|integer|min:0',
            'productPrice'       => 'required|numeric|min:0',
        ]);

        $product = Product::create([
            'name'        => ucwords(trim($this->productName)),
            'description' => trim((string) $this->productDescription),
            'stocks'      => (int)   $this->productStocks,
            'sold'        => 0,
            'is_in_stock' => (int)   $this->productStocks > 0,
            'category'    => $this->productCategory,
            'price'       => (float) $this->productPrice,
        ]);

        if (method_exists($this, 'loadData')) {
            $this->loadData();
        }

        // If a target slot was specified, fill it; otherwise use addProductToCart
        // so the new product lands in the cart automatically.
        if ($this->productTargetIndex !== null && isset($this->orderItems[$this->productTargetIndex])) {
            $this->selectProduct($product->id, $this->productTargetIndex);
        } else {
            $this->addProductToCart($product->id);
        }

        $this->closeProductForm();
        $this->dispatch('show-success', ['message' => __('Product created successfully!')]);
    }
}
