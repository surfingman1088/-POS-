<?php
namespace App\Livewire\Order;

use App\Helpers\PaymentImageHelper;
use App\Livewire\Concerns\HasConfirmData;
use App\Livewire\Concerns\HasOrderForm;
use App\Models\Customer;
use App\Models\DiscountPreset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Products\InventoryService;
use App\Services\System\AuditLogsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use HasOrderForm, HasConfirmData, WithFileUploads;

    public string  $orderNumber          = '';
    public string  $orderType            = 'walk_in';
    public string  $paymentType          = 'cash';
    public ?int    $selectedEmployeeId   = null;
    public ?int    $selectedCustomerId   = null;
    public bool    $isCreatingNewCustomer = false;
    public string  $customerName         = '';
    public string  $customerUnit         = '';
    public string  $customerAddress      = '';
    public string  $customerContact      = '';
    public string  $customerSearch       = '';
    public string  $employeeSearch       = '';
    public string  $productSearch        = '';
    public array   $orderItems           = [];
    public array   $errorFields          = [];
    public bool    $showConfirmModal     = false;
    public string  $modalMode            = 'confirm';
    public array $confirmData = [];
    public ?int    $discountPresetId     = null;
    public string  $discountType         = 'none';
    public string|float $discountValue   = 0;
    public array   $discountPresets      = [];

    // Walk-in payment
    public ?float  $amountReceived    = null;
    public float   $changeAmount      = 0;
    public bool    $processingPayment = false;
    public ?string $currentImage      = null;

    // Proof of payment (not cash)
    public $proofOfPayment = null;

    // Product form
    public bool         $showProductForm    = false;
    public ?int         $productTargetIndex = null;
    public string       $productName        = '';
    public string       $productDescription = '';
    public mixed        $productCategory    = '';
    public string|int   $productStocks      = 1;
    public string|float $productPrice       = 0;
    public string       $defaultPaymentType  = 'cash';

    protected $rules = [
        'orderType'               => 'required|in:deliver,walk_in',
        'paymentType'             => 'required|string',
        'selectedEmployeeId'      => 'nullable|exists:employees,id',
        'discountPresetId'        => 'nullable|exists:discount_preset,id',
        'discountType'            => 'required|in:percentage,fixed,none',
        'discountValue'           => 'nullable|numeric|min:0',
        'selectedCustomerId'      => 'nullable|exists:customers,id',
        'customerName'            => 'nullable|string|max:255',
        'customerUnit'            => 'nullable|string|max:255',
        'customerAddress'         => 'nullable|string|max:255',
        'customerContact'         => 'nullable|string|max:20',
        'orderItems'              => 'required|array|min:1',
        'orderItems.*.product_id' => 'required|exists:products,id',
        'orderItems.*.quantity'   => 'required|integer|min:1',
        'orderItems.*.price'      => 'nullable|numeric|min:0',
        'orderItems.*.discount'   => 'nullable|numeric|min:0',
        'orderItems.*.is_free'    => 'nullable|boolean',
        'proofOfPayment'          => 'nullable|image|mimes:png,jpg,jpeg,webp|max:10240',
    ];

    public function mount(): void
    {
        $this->defaultPaymentType = config('storeconfig.default_payment_type');
        $this->orderType   = config('storeconfig.default_order_type',   'walk_in');
        $this->paymentType = $this->defaultPaymentType;
        $this->orderNumber = $this->generateReceiptNumber();
        $this->loadDiscountPresets();
        $this->addOrderItem();
    }

    private function loadDiscountPresets(): void
    {
        $this->discountPresets = DiscountPreset::query()
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'type', 'value'])
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
            ->where('is_active', true)
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

    private function resolveDiscountConfig(): array
    {
        $presetId = null;
        $type = (string) $this->discountType;
        $value = (float) $this->discountValue;

        if ($this->discountPresetId) {
            $preset = collect($this->discountPresets)
                ->first(fn ($item) => (int) ($item['id'] ?? 0) === (int) $this->discountPresetId);

            if (! $preset) {
                $model = DiscountPreset::query()->whereKey((int) $this->discountPresetId)->first(['id', 'type', 'value']);
                if ($model) {
                    $preset = [
                        'id' => $model->id,
                        'type' => $model->type,
                        'value' => $model->value,
                    ];
                }
            }

            if ($preset) {
                $presetId = (int) ($preset['id'] ?? 0);
                $type = (string) ($preset['type'] ?? 'none');
                $value = (float) ($preset['value'] ?? 0);
            }
        }

        if (! in_array($type, ['percentage', 'fixed'], true)) {
            return ['preset_id' => null, 'type' => 'none', 'value' => 0.0];
        }

        return [
            'preset_id' => $presetId,
            'type' => $type,
            'value' => $value,
        ];
    }

    private function calculateOrderDiscountFor(float $baseTotal, string $type, float $value): float
    {
        if ($baseTotal <= 0) {
            return 0;
        }

        return match ($type) {
            'percentage' => min($baseTotal, max(0, $baseTotal * ($value / 100))),
            'fixed' => min($baseTotal, max(0, $value)),
            default => 0,
        };
    }

    private function syncDiscountFromSelection(): array
    {
        $config = $this->resolveDiscountConfig();

        $this->discountPresetId = $config['preset_id'];
        $this->discountType = $config['type'];
        $this->discountValue = (float) $config['value'];

        return $config;
    }

    public function getOrderDiscountAmountProperty(): float
    {
        $config = $this->resolveDiscountConfig();
        return $this->calculateOrderDiscountFor((float) $this->totalAmount, $config['type'], (float) $config['value']);
    }

    public function getFinalTotalProperty(): float
    {
        return max(0, (float) $this->totalAmount - (float) $this->orderDiscountAmount);
    }

    public function getOrderDiscountDisplayProperty(): string
    {
        $amount = (float) $this->orderDiscountAmount;
        if ($amount <= 0) {
            return '-₱0';
        }

        $formattedAmount = '₱' . $this->formatMoneyCompact($amount);

        if ($this->discountType === 'percentage') {
            $percent = rtrim(rtrim(number_format((float) $this->discountValue, 2, '.', ''), '0'), '.');
            return '-' . $formattedAmount . ' (' . $percent . '%)';
        }

        return '-' . $formattedAmount;
    }

    private function formatMoneyCompact(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    // ── Lifecycle ──────────────────────────────────────────────────

    public function updatedOrderItems($value, $key): void
    {
        if (! $key) {
            return;
        }
        
        $this->handleUpdatedOrderItem($value, $key);
    }

    public function updatedOrderType(): void
    {
        if ($this->orderType === 'walk_in') {
            $this->selectedEmployeeId    = null;
            $this->selectedCustomerId    = null;
            $this->isCreatingNewCustomer = false;
            $this->customerName = $this->customerUnit = $this->customerAddress = $this->customerContact = '';
            $this->dispatch('customer-validation-clear');
        }
        $this->resetErrorBag();
    }

    public function updatedPaymentType(): void
    {
        // Only clear the proof when switching TO cash — switching between
        // non-cash methods (e.g. gcash -> maya) should keep whatever was
        // already uploaded.
        if ($this->paymentType === 'cash') {
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

    public function updatedAmountReceived(): void
    {
        if ($this->amountReceived === '' || $this->amountReceived === null) {
            $this->changeAmount = 0;
            $this->resetErrorBag(['amountReceived']);
            return;
        }

        $received           = is_numeric($this->amountReceived) ? (float) $this->amountReceived : 0;
        $this->changeAmount = max(0, $received - $this->finalTotal);

        if ($received >= $this->finalTotal) {
            $this->resetErrorBag(['amountReceived']);
        }
    }

    // ── Modal ──────────────────────────────────────────────────────

    public function openSaveConfirmation(): void
    {
        $this->syncDiscountFromSelection();

        if (! $this->validateSubmissionRequirements()) {
            $this->showConfirmModal = false;
            return;
        }

        $this->dispatch('customer-validation-clear');

        if ($this->orderType === 'walk_in') {
            $this->modalMode      = 'walkin';
            $this->amountReceived = null;
            $this->changeAmount   = 0;
            $this->currentImage   = PaymentImageHelper::getPaymentImageUrl();
        } else {
            $this->modalMode = 'confirm';
        }

        $this->confirmData = $this->buildConfirmData();
        $this->showConfirmModal = true;
    }

    public function closeSaveConfirmation(): void
    {
        $this->showConfirmModal  = false;
        $this->processingPayment = false;
        $this->amountReceived    = null;
        $this->changeAmount      = 0;
    }

    /** Delivery: confirm & save */
    public function saveSalesRecord(): void
    {
        $this->showConfirmModal = false;
        $this->createOrder();
    }

    /** Walk-in: validate cash / non-cash then save */
    public function processPayment(): void
    {
        $this->processingPayment = true;

        if ($this->paymentType === 'cash' && (float) $this->finalTotal > 0) {
            $this->validate([
                'amountReceived' => [
                    'required', 'numeric', 'min:' . $this->finalTotal,
                ],
            ], [
                'amountReceived.required' => __('Please enter the amount received.'),
                'amountReceived.min'      => __('Amount received must be at least ₱') . number_format($this->finalTotal, 2),
            ]);
        }

        if ($this->paymentType !== 'cash') {
            $this->validateOnly('proofOfPayment', [
                'proofOfPayment' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:10240',
            ]);
        }

        $this->showConfirmModal = false;
        $this->createOrder();
    }

    // ── Validation ─────────────────────────────────────────────────

    protected function getSubmissionRules(): array
    {
        $rules = $this->rules;
        $allowedPaymentTypes = array_values(array_unique(array_filter(array_merge(
            [$this->defaultPaymentType, 'cash', 'gcash'],
            (array) config('storeconfig.other_payment_types', [])
        ))));

        $rules['paymentType'] = 'required|in:' . implode(',', $allowedPaymentTypes);

        if ($this->orderType === 'deliver') {
            $rules['selectedEmployeeId'] = 'required|exists:employees,id';

            if ($this->isCreatingNewCustomer) {
                $rules['customerName']       = 'required|string|max:255';
                $rules['customerContact']    = 'nullable|string|max:20';
                $rules['customerAddress']    = 'required|string|max:255';
                $rules['selectedCustomerId'] = 'nullable';
            } else {
                $rules['selectedCustomerId'] = 'required|exists:customers,id';
            }
        }

        return $rules;
    }

    protected function validateSubmissionRequirements(): bool
    {
        try {
            $this->validate($this->getSubmissionRules());
        } catch (ValidationException $e) {
            $this->errorFields = array_keys($e->errors());
            $this->dispatch('form-validation-failed', errorFields: $this->errorFields);

            $customerFields = ['selectedCustomerId', 'customerName', 'customerAddress'];
            if (array_intersect($customerFields, $this->errorFields)) {
                $this->dispatch('customer-validation-error');
            }

            return false;
        }

        return true;
    }

    // ── Receipt number ─────────────────────────────────────────────

    public function generateReceiptNumber(): string
    {
        $datePart = now()->format('ymd');
        $prefix   = "OR{$datePart}";

        $last = Order::query()
            ->where('receipt_number', 'like', "{$prefix}%")
            ->latest('id')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Save ───────────────────────────────────────────────────────

    public function createOrder(): void
    {
        $discountConfig = $this->syncDiscountFromSelection();
        $finalTotal = max(
            0,
            (float) $this->totalAmount - $this->calculateOrderDiscountFor((float) $this->totalAmount, $discountConfig['type'], (float) $discountConfig['value'])
        );

        // Stock pre-check
        foreach ($this->orderItems as $index => $item) {
            if (! ($item['product_id'] ?? null)) {
                $this->dispatch('form-validation-failed', errorFields: ["orderItems.{$index}.product_id"]);
                return;
            }
            $product  = Product::query()->whereKey($item['product_id'])->first();
            $quantity = (int) ($item['quantity'] ?? 0);

            if (! $product || ! $product->is_in_stock || $product->stocks <= 0) {
                $this->dispatch('form-validation-failed', errorFields: ["orderItems.{$index}.product_id"]);
                return;
            }
            if ($quantity < 1 || $quantity > (int) $product->stocks) {
                $this->dispatch('form-validation-failed', errorFields: ["orderItems.{$index}.quantity"]);
                return;
            }
        }

        // Store proof image (if any). Generalized from "=== 'gcash'" to
        // "!== 'cash'" so any configured non-cash method can attach proof.
        $proofPath     = null;
        $paymentStatus = 'unpaid';

        if ($this->paymentType === 'cash') {
            // Cash walk-in is always paid at POS
            $paymentStatus = $this->orderType === 'walk_in' ? 'paid' : 'unpaid';
        } elseif ($this->proofOfPayment) {
            $ext          = strtolower($this->proofOfPayment->getClientOriginalExtension() ?: 'png');
            $dir          = 'order/' . $this->orderNumber;
            $proofPath    = $this->proofOfPayment->storeAs($dir, $this->orderNumber . '.' . $ext, 'public');
            $paymentStatus = 'paid';
        }

        DB::transaction(function () use ($proofPath, $paymentStatus, $discountConfig, $finalTotal) {
            $inventory = app(InventoryService::class);
            $customerId = $this->persistCustomer();
            $isWalkIn   = $this->orderType === 'walk_in';

            $order = Order::create([
                'customer_id'     => $customerId,
                'created_by'      => Auth::id(),
                'delivered_by'    => $isWalkIn ? null : $this->selectedEmployeeId,
            'order_total'     => $finalTotal,
            'discount_preset_id' => $discountConfig['type'] === 'none' ? null : $discountConfig['preset_id'],
            'discount_type'   => $discountConfig['type'],
            'discount_value'  => $discountConfig['type'] === 'none' ? 0 : (float) $discountConfig['value'],
                'order_type'      => $this->orderType,
                'payment_type'    => $this->paymentType,
                'payment_status'  => $paymentStatus,
                'status'          => $isWalkIn ? 'completed' : 'pending',
                'receipt_number'  => $this->orderNumber,
                'amount_received' => $isWalkIn ? $this->amountReceived : null,
                'change_amount'   => $isWalkIn ? $this->changeAmount   : null,
                'proof_of_payment'=> $proofPath,
            ]);

            foreach ($this->orderItems as $item) {
                if (! ($item['product_id'] ?? null)) continue;

                $qty      = (int) $item['quantity'];

                $isFree    = (bool) ($item['is_free'] ?? false);
                $unitPrice = $isFree ? 0 : max(0, (float) ($item['price'] ?? 0));
                $discount  = $isFree ? 0 : min(max(0, (float) ($item['discount'] ?? 0)), $qty * $unitPrice);
                $lineTotal = max(0, ($qty * $unitPrice) - $discount);

                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $qty,
                    'unit_price'  => $unitPrice,
                    'discount_amount' => $discount,
                    'total_price' => $lineTotal,
                ]);

                $inventory->deduct(
                    (int) $item['product_id'],
                    $qty,
                    'order_created',
                    $order,
                    __('Order #:receipt created.', ['receipt' => $order->receipt_number])
                );
            }

            // Record audit log
            app(AuditLogsService::class)->recordOrderCreated(Auth::user(), $order, request());
        });

        session()->flash('success', __('Order created successfully!'));
        $this->redirect(route('orders'));
    }

    private function persistCustomer(): ?int
    {
        if ($this->orderType !== 'deliver') return null;

        if ($this->isCreatingNewCustomer) {
            $c = Customer::create([
                'name'           => ucwords(trim($this->customerName)),
                'unit'           => ucwords(trim($this->customerUnit)),
                'address'        => ucwords(trim($this->customerAddress)),
                'contact_number' => trim($this->customerContact) ?: null,
                'created_by'     => Auth::id(),
            ]);
            return $c->id;
        }

        if ($this->selectedCustomerId) {
            Customer::query()->whereKey($this->selectedCustomerId)->first()?->update([
                'name'           => ucwords(trim($this->customerName)),
                'unit'           => ucwords(trim($this->customerUnit)),
                'address'        => ucwords(trim($this->customerAddress)),
                'contact_number' => trim($this->customerContact) ?: null,
            ]);
        }

        return $this->selectedCustomerId;
    }

    public function render()
    {
        return view('livewire.order.create');
    }
}