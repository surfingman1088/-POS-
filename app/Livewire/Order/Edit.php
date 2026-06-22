<?php

namespace App\Livewire\Order;

use App\Livewire\Concerns\HasConfirmData;
use App\Livewire\Concerns\HasOrderForm;
use App\Models\Customer;
use App\Models\DiscountPreset;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Products\InventoryService;
use App\Services\System\AuditLogsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use HasOrderForm, HasConfirmData, WithFileUploads;

    protected $listeners = [
        'order-refunded' => 'handleOrderRefunded',
    ];

    public Order $order;

    public string $status         = '';
    public string $payment_type   = '';
    public string $payment_status = 'unpaid';
    public string $order_type     = '';
    public ?int   $discountPresetId = null;
    public string $discountType     = 'none';
    public string|float $discountValue = 0;
    public array  $discountPresets  = [];
    public $delivered_by          = null;
    public $customer_id           = null;

    public $proofOfPayment        = null;
    public ?string $existingProof = null;

    // HasOrderForm required properties
    public ?int   $selectedEmployeeId    = null;
    public ?int   $selectedCustomerId    = null;
    public bool   $isCreatingNewCustomer = false;
    public string $customerName          = '';
    public string $customerUnit          = '';
    public string $customerAddress       = '';
    public string $customerContact       = '';
    public string $customerSearch        = '';
    public string $employeeSearch        = '';
    public string $productSearch         = '';
    public array  $orderItems            = [];
    public bool   $showConfirmModal      = false;
    public string $orderType             = '';
    public array  $confirmData           = [];

    // HasOrderForm product-form properties (not used in Edit but trait requires them)
    public bool         $showProductForm    = false;
    public ?int         $productTargetIndex = null;
    public string       $productName        = '';
    public string       $productDescription = '';
    public mixed        $productCategory    = '';
    public string|int   $productStocks      = 1;
    public string|float $productPrice       = 0;

    private function lockedStatuses(): array
    {
        return (array) config('storeconfig.order_edit_lock_status', [
            'delivered', 'completed', 'cancelled',
        ]);
    }

    public function mount(Order $order): void
    {
        // Allow editing completed/paid orders so refunds can be processed,
        // but lock delivered and cancelled.
        $locked = array_diff($this->lockedStatuses(), ['completed']);

        if (in_array($order->status, $locked, true)) {
            session()->flash('error', __('Order #:receipt cannot be edited once it is :status.', [
                'receipt' => $order->receipt_number,
                'status'  => $order->status,
            ]));

            $this->redirect(route('orders'), navigate: true);
            return;
        }

        $this->order          = $order->load(['customer', 'employee', 'orderItems.product']);
        $this->status         = $order->status;
        $this->payment_type   = $order->payment_type  ?? 'cash';
        $this->payment_status = $order->payment_status ?? 'unpaid';
        $this->order_type     = $order->order_type;
        $this->orderType      = $order->order_type;
        $this->discountPresetId = $order->discount_preset_id;
        $this->discountType = $order->discount_type ?? 'none';
        $this->discountValue = (float) ($order->discount_value ?? 0);
        $this->delivered_by   = $order->delivered_by;
        $this->customer_id    = $order->customer_id;
        $this->selectedCustomerId = $order->customer_id;
        $this->existingProof  = $order->proof_of_payment;
        $this->loadDiscountPresets();

        if ($this->customer_id) {
            $c = Customer::query()->whereKey($this->customer_id)->first();
            if ($c) {
                $this->customerName    = $c->name           ?? '';
                $this->customerUnit    = $c->unit           ?? '';
                $this->customerAddress = $c->address        ?? '';
                $this->customerContact = $c->contact_number ?? '';
            }
        }

        $this->loadOrderItems();
    }

    private function loadDiscountPresets(): void
    {
        $query = DiscountPreset::query()->orderBy('name', 'asc');

        if ($this->discountPresetId) {
            $query->where(function ($sub) {
                $sub->where('is_active', true)
                    ->orWhere('id', $this->discountPresetId);
            });
        } else {
            $query->where('is_active', true);
        }

        $this->discountPresets = $query
            ->get(['id', 'name', 'type', 'value', 'is_active'])
            ->toArray();
    }

    public function updatedDiscountPresetId($value): void
    {
        $presetId = is_numeric($value) ? (int) $value : null;

        if (! $presetId) {
            $this->discountPresetId = null;
            $this->discountType = 'none';
            $this->discountValue = 0;
            return;
        }

        $preset = DiscountPreset::query()
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhere('id', $this->discountPresetId);
            })
            ->whereKey($presetId)
            ->first();

        if (! $preset) {
            $this->discountPresetId = null;
            $this->discountType = 'none';
            $this->discountValue = 0;
            return;
        }

        $this->discountPresetId = $preset->id;
        $this->discountType = $preset->type;
        $this->discountValue = (float) $preset->value;
    }

    // ── Lifecycle ──────────────────────────────────────────────────

    public function updatedOrderItems($value, $key): void
    {
        if (! $key) {
            return;
        }
        
        $this->handleUpdatedOrderItem($value, $key);
    }

    public function updatedOrderType($value): void
    {
        $this->order_type = $value;
        if ($value === 'walk_in') {
            $this->delivered_by       = null;
            $this->customer_id        = null;
            $this->selectedCustomerId = null;
            $this->dispatch('customer-validation-clear');
        }
    }

    /**
     * When user tries to set payment_status = 'refunded' via the dropdown,
     * Previously this intercepted attempts to set `refunded` and opened the
     * refund modal. Refunds are now handled exclusively by the Refund modal
     * component; the Edit component should only update order fields.
     */

    public function updatedPaymentType(): void
    {
        if ($this->payment_type === 'cash') {
            $this->proofOfPayment = null;
        }
    }

    public function updatedProofOfPayment(): void
    {
        $this->validateOnly('proofOfPayment', [
            'proofOfPayment' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:10240',
        ]);
    }

    public function removeProof(): void
    {
        $this->proofOfPayment = null;
        $this->resetErrorBag(['proofOfPayment']);
    }

    public function deleteExistingProof(): void
    {
        if ($this->existingProof) {
            Storage::disk('public')->delete($this->existingProof);
        }
        $this->existingProof = null;
        Order::query()->where('id', $this->order->id)->update(['proof_of_payment' => null]);
    }

    // ── Employee / Customer (override trait) ───────────────────────

    public function selectEmployee(int $employeeId): void
    {
        $employee = Employee::query()
            ->where('status', 'active')->where('is_archived', false)
            ->whereKey($employeeId)->first();
        if (! $employee) return;

        $this->delivered_by   = $employee->id;
        $this->employeeSearch = '';
        $this->resetErrorBag(['delivered_by']);
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->first();
        if (! $customer) return;

        $this->customer_id        = $customer->id;
        $this->selectedCustomerId = $customer->id;
        $this->customerName        = $customer->name           ?? '';
        $this->customerUnit        = $customer->unit           ?? '';
        $this->customerAddress     = $customer->address        ?? '';
        $this->customerContact     = $customer->contact_number ?? '';
        $this->isCreatingNewCustomer = false;
        $this->customerSearch      = '';
        $this->resetErrorBag(['customer_id', 'selectedCustomerId', 'customerName', 'customerAddress', 'customerContact']);
        $this->dispatch('customer-validation-clear');
    }

    // ── Modal ──────────────────────────────────────────────────────

    public function openSaveConfirmation(): void
    {
        $this->dispatch('customer-validation-clear');
        $this->confirmData = $this->buildConfirmData();
        $this->showConfirmModal = true;
    }

    public function closeSaveConfirmation(): void
    {
        $this->showConfirmModal = false;
    }

    public function saveSalesRecord(): void
    {
        $this->showConfirmModal = false;
        $this->save();
    }

    /**
     * Called when the Refund component emits 'order-refunded'.
     * Re-loads the order so payment_status and refunded_quantity are fresh.
     */
    public function handleOrderRefunded(int $orderId = 0): void
    {
        if ($orderId && $orderId !== $this->order->id) return;

        $this->order = Order::with(['customer', 'employee', 'orderItems.product'])
            ->find($this->order->id);

        $this->payment_status = $this->order->payment_status;
        $this->loadOrderItems();
        $this->dispatch('show-success', ['message' => __('Refund processed. Review and save the order if needed.')]);
    }

    // ── Computed ───────────────────────────────────────────────────

    public function getIsLockedProperty(): bool
    {
        $locked = array_diff($this->lockedStatuses(), ['completed']);
        return in_array($this->order->status, $locked, true);
    }

    public function getEditedTotalProperty(): float
    {
        return (float) collect($this->orderItems)->sum(fn ($item) => (float) ($item['total'] ?? 0));
    }

    public function getOrderDiscountAmountProperty(): float
    {
        $baseTotal = (float) $this->editedTotal;
        if ($baseTotal <= 0) {
            return 0;
        }

        return match ($this->discountType) {
            'percentage' => min($baseTotal, max(0, $baseTotal * ((float) $this->discountValue / 100))),
            'fixed' => min($baseTotal, max(0, (float) $this->discountValue)),
            default => 0,
        };
    }

    public function getDiscountedEditedTotalProperty(): float
    {
        return max(0, (float) $this->editedTotal - (float) $this->orderDiscountAmount);
    }

    public function getShowQrProperty(): bool
    {
        return $this->order_type    === 'walk_in'
            && $this->payment_type  === 'gcash'
            && $this->payment_status === 'unpaid';
    }

    // ── Items ──────────────────────────────────────────────────────

    private function loadOrderItems(): void
    {
        $this->orderItems = $this->order->orderItems
            ->map(fn ($item) => [
                'id'                => $item->id,
                'product_id'        => $item->product_id,
                'product_name'      => $item->product?->name ?? 'Product #' . $item->product_id,
                'quantity'          => (int) $item->quantity,
                'refunded_quantity' => (int) ($item->refunded_quantity ?? 0),
                'price'             => (float) $item->unit_price,
                'discount'          => (float) ($item->discount_amount ?? 0),
                'stocks'            => $item->product?->stocks ?? 0,
                'original_price'    => (float) $item->unit_price,
                'is_free'           => (float) $item->total_price <= 0,
                'total'             => (float) $item->total_price,
            ])
            ->values()
            ->all();

    }

    // ── Save ───────────────────────────────────────────────────────

    public function save(): void
    {
        if ($this->isLocked) {
            session()->flash('error', __('This order cannot be edited.'));
            return;
        }

        $this->validate([
            'status'         => 'required|in:pending,preparing,in_transit,delivered,completed,cancelled',
            'payment_type'   => 'required|string',
            'payment_status' => 'required|in:unpaid,paid,refunded',
            'order_type'     => 'required|in:walk_in,deliver',
            'discountPresetId' => 'nullable|exists:discount_preset,id',
            'discountType'     => 'required|in:percentage,fixed,none',
            'discountValue'    => 'nullable|numeric|min:0',
            'delivered_by'   => 'nullable|exists:employees,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'orderItems'              => 'required|array|min:1',
            'orderItems.*.product_id' => 'required|exists:products,id',
            'orderItems.*.quantity'   => 'required|integer|min:1',
            'orderItems.*.price'      => 'required|numeric|min:0',
            'orderItems.*.discount'   => 'nullable|numeric|min:0',
            'orderItems.*.is_free'    => 'nullable|boolean',
            'proofOfPayment'          => 'nullable|image|mimes:png,jpg,jpeg,webp|max:10240',
        ]);

        foreach (array_keys($this->orderItems) as $index) {
            $this->calculateItemTotal($index);
        }

        // Handle proof upload
        $proofPath = $this->existingProof;
        if ($this->payment_type !== 'cash' && $this->proofOfPayment) {
            if ($this->existingProof) {
                Storage::disk('public')->delete($this->existingProof);
            }
            $ext       = strtolower($this->proofOfPayment->getClientOriginalExtension() ?: 'png');
            $dir       = 'order/' . $this->order->receipt_number;
            $proofPath = $this->proofOfPayment->storeAs($dir, $this->order->receipt_number . '.' . $ext, 'public');
            if ($this->payment_status === 'unpaid') {
                $this->payment_status = 'paid';
            }
        }

        if ($this->order_type === 'walk_in' && $this->payment_status === 'paid') {
            $this->status = 'completed';
        }

        DB::transaction(function () use ($proofPath) {
            $oldStatus = $this->order->status;
            $newStatus = $this->status;

            // Build edited items using authoritative refunded_quantity from DB
            $existingItemsFromDb = OrderItem::query()->where('order_id', $this->order->id)
                ->get()
                ->keyBy('product_id');

            $newItems = collect($this->orderItems)
                ->map(function ($item) use ($existingItemsFromDb) {
                    $productId        = (int) $item['product_id'];
                    $existing         = $existingItemsFromDb->get($productId);
                    $refundedQty      = (int) ($existing?->refunded_quantity ?? 0);
                    $newQty           = max(1, (int) $item['quantity']);

                    return [
                        'product_id'        => $productId,
                        'quantity'          => $newQty,
                        'refunded_quantity' => $refundedQty, // carry through — never modified here
                        'price'             => max(0, (float) $item['price']),
                        'discount'          => max(0, (float) ($item['discount'] ?? 0)),
                        'is_free'           => (bool) ($item['is_free'] ?? false),
                        'total'             => max(0, (float) ($item['total'] ?? 0)),
                    ];
                })
                ->values()
                ->all();

            // Reconcile inventory
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                // Cancellation: restore ALL net quantities back to stock
                $this->restoreOriginalInventory($existingItemsFromDb);
            } else {
                // Normal edit: sync old net qty to new net qty per product
                $this->reconcileInventory($newItems, $existingItemsFromDb);
            }

            // Upsert order items (preserves refunded_quantity)
            $newProductIds = collect($newItems)->pluck('product_id')->all();

            // Delete rows that were removed from the order
            foreach ($existingItemsFromDb as $existingItem) {
                if (! in_array((int) $existingItem->product_id, $newProductIds, true)) {
                    OrderItem::query()
                        ->where('order_id', $this->order->id)
                        ->where('product_id', (int) $existingItem->product_id)
                        ->delete();
                }
            }

            foreach ($newItems as $item) {
                $existing = $existingItemsFromDb->get($item['product_id']);

                if ($existing) {
                    // Update in place — refunded_quantity is intentionally NOT touched
                    $existing->update([
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['price'],
                        'discount_amount' => min(max(0, (float) ($item['discount'] ?? 0)), $item['quantity'] * $item['price']),
                        'total_price' => $item['total'],
                    ]);
                } else {
                    $discount = min(max(0, (float) ($item['discount'] ?? 0)), $item['quantity'] * $item['price']);
                    OrderItem::create([
                        'order_id'          => $this->order->id,
                        'product_id'        => $item['product_id'],
                        'quantity'          => $item['quantity'],
                        'unit_price'        => $item['price'],
                        'discount_amount'   => $discount,
                        'total_price'       => $item['total'],
                        'refunded_quantity' => 0,
                    ]);
                }
            }

            $oldSnapshot = $this->order->toArray();

            // Update the order
            $this->order->update([
                'status'           => $newStatus,
                'payment_type'     => $this->payment_type,
                'payment_status'   => $this->payment_status,
                'order_type'       => $this->order_type,
                'delivered_by'     => $this->delivered_by ?: null,
                'customer_id'      => $this->customer_id  ?: null,
                'order_total'      => $this->discountedEditedTotal,
                'discount_preset_id' => $this->discountType === 'none' ? null : $this->discountPresetId,
                'discount_type'    => $this->discountType,
                'discount_value'   => $this->discountType === 'none' ? 0 : (float) $this->discountValue,
                'proof_of_payment' => $proofPath,
            ]);

            // ── Audit ──────────────────────────────────────────────────
            $action = $newStatus === 'cancelled' && $oldStatus !== 'cancelled'
                ? 'order.cancelled'
                : 'order.updated';

            app(AuditLogsService::class)->record(
                $action,
                Auth::user(),
                $this->order,
                $oldSnapshot,
                [
                    'receipt_number' => $this->order->receipt_number,
                    'status'         => $newStatus,
                    'payment_type'   => $this->payment_type,
                    'payment_status' => $this->payment_status,
                    'order_type'     => $this->order_type,
                    'order_total'    => $this->editedTotal,
                ],
                request()
            );
        });

        $this->order->refresh()->load(['orderItems.product']);
        $this->loadOrderItems();

        session()->flash('success', __('Order #:receipt updated.', ['receipt' => $this->order->receipt_number]));
        $this->redirect(route('orders'), navigate: true);
    }

    public function cancel(): void
    {
        if ($this->isLocked) {
            session()->flash('error', __('This order cannot be edited.'));
            return;
        } elseif ($this->order->status === 'cancelled') {
            session()->flash('info', __('This order is already cancelled.'));
            return;
        } else {
            $this->status = 'cancelled';
            session()->flash('success', __('Order #:receipt cancelled.', ['receipt' => $this->order->receipt_number]));
            $this->save();
        }

        $this->redirect(route('orders'), navigate: true);
    }

    // ── Inventory helpers ──────────────────────────────────────────

    /**
     * Sync inventory by product using net quantities (ordered - refunded).
     *
     * @param array $newItems From the form (product_id, quantity, refunded_quantity)
     * @param \Illuminate\Support\Collection $existingItemsFromDb Keyed by product_id
     */
    private function reconcileInventory(array $newItems, $existingItemsFromDb): void
    {
        $inventory = app(InventoryService::class);

        // Build new net totals from edited items.
        $newNetTotals = [];
        foreach ($newItems as $item) {
            $id          = (int) $item['product_id'];
            $net         = max(0, (int) $item['quantity'] - (int) ($item['refunded_quantity'] ?? 0));
            $newNetTotals[$id] = ($newNetTotals[$id] ?? 0) + $net;
        }

        // Build old net totals from DB state.
        $oldNetTotals = [];
        foreach ($existingItemsFromDb as $existing) {
            $id  = (int) $existing->product_id;
            $net = max(0, (int) $existing->quantity - (int) ($existing->refunded_quantity ?? 0));
            $oldNetTotals[$id] = ($oldNetTotals[$id] ?? 0) + $net;
        }

        $productIds   = array_unique(array_merge(array_keys($oldNetTotals), array_keys($newNetTotals)));

        foreach ($productIds as $productId) {
            $inventory->sync(
                (int) $productId,
                (int) ($oldNetTotals[$productId] ?? 0),
                (int) ($newNetTotals[$productId] ?? 0),
                'order_updated',
                $this->order,
                __('Order #:receipt updated.', ['receipt' => $this->order->receipt_number])
            );
        }
    }

    /**
     * Called when order is cancelled.
     * Restores each item's NET quantity (ordered - already_refunded) back to stock.
     * Units already refunded were already returned by the Refund component — don't double-restore.
     *
     * @param \Illuminate\Support\Collection $existingItemsFromDb Keyed by product_id
     */
    private function restoreOriginalInventory($existingItemsFromDb): void
    {
        $inventory = app(InventoryService::class);

        foreach ($existingItemsFromDb as $item) {
            $toRestore = max(0, (int) $item->quantity - (int) ($item->refunded_quantity ?? 0));
            if ($toRestore <= 0) continue;

            $inventory->restore(
                (int) $item->product_id,
                $toRestore,
                'order_cancelled',
                $this->order,
                __('Order #:receipt cancelled.', ['receipt' => $this->order->receipt_number])
            );
        }
    }

    // ── Confirm data helper ────────────────────────────────────────

    private function buildConfirmData(): array
    {
        $loc = app()->getLocale() === 'cn' ? 'zh_CN' : app()->getLocale();
        $paymentType = strtolower(trim((string) $this->payment_type));

        return [
            'receiptNumber'      => $this->order->receipt_number,
            'reviewDateTime'     => $this->order->created_at->locale($loc)->isoFormat('LLLL'),
            'orderType'          => $this->order_type === 'deliver' ? __('Delivery') : __('Walk-In'),
            'paymentLabel'       => match ($paymentType) {
                'cash'  => __('Cash'),
                'gcash' => __('GCash'),
                default => ucwords(str_replace('_', ' ', $paymentType)),
            },
            'paymentStatusLabel' => match ($this->payment_status) {
                'paid'     => __('Paid'),
                'refunded' => __('Refunded'),
                default    => __('Unpaid'),
            },
            'statusLabel'        => match ($this->status) {
                'completed'  => __('Completed'),
                'pending'    => __('Pending'),
                'preparing'  => __('Preparing'),
                'in_transit' => __('In transit'),
                'delivered'  => __('Delivered'),
                'cancelled'  => __('Cancelled'),
                default      => ucfirst(str_replace('_', ' ', $this->status)),
            },
            'statusKey'          => $this->status,
            'deliveredBy'        => optional($this->selectedEmployee)->name,
            'customerName'       => $this->customerName,
            'customerContact'    => $this->customerContact,
            'customerUnit'       => $this->customerUnit,
            'customerAddress'    => $this->customerAddress,
            'items'              => $this->orderItems,
            'totalAmount'        => $this->discountedEditedTotal,
            'subtotalAmount'     => $this->editedTotal,
            'discountType'       => $this->discountType,
            'discountValue'      => (float) $this->discountValue,
            'discountAmount'     => $this->orderDiscountAmount,
        ];
    }

    // ── Render ─────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.order.edit', [
            'selectedCustomer' => $this->customer_id
                ? Customer::query()->whereKey($this->customer_id)->first()
                : null,
            'selectedEmployee' => $this->delivered_by
                ? Employee::query()->whereKey($this->delivered_by)->first()
                : null,
        ]);
    }
}
