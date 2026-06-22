<?php
namespace App\Livewire\Order;

use App\Livewire\Concerns\HasConfirmData;
use App\Livewire\Concerns\HasOrderForm;
use App\Models\Customer;
use App\Models\DiscountPreset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\System\AuditLogsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Add extends Component
{
    use HasOrderForm, HasConfirmData, WithFileUploads;

    // ── Form state ─────────────────────────────────────────────────
    public string  $receiptNumber         = '';
    public string  $saleDate              = '';
    public string  $orderType             = 'walk_in';
    public string  $paymentType           = 'cash';

    /**
     * payment_status: 'unpaid' | 'paid' | 'refunded'
     * Replaces the old boolean $isPaid.
     */
    public string  $paymentStatus         = 'paid';
    public ?int    $discountPresetId      = null;
    public string  $discountType          = 'none';
    public string|float $discountValue    = 0;
    public array   $discountPresets       = [];

    public string  $status                = 'completed';
    public ?int    $selectedEmployeeId    = null;
    public ?int    $selectedCustomerId    = null;
    public bool    $isCreatingNewCustomer = false;
    public string  $customerName          = '';
    public string  $customerUnit          = '';
    public string  $customerAddress       = '';
    public string  $customerContact       = '';
    public string  $customerSearch        = '';
    public string  $employeeSearch        = '';
    public string  $productSearch         = '';
    public array   $orderItems            = [];
    public array   $errorFields           = [];
    public bool    $showConfirmModal      = false;

    public array $confirmData = [];

    // Proof of payment
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
        'receiptNumber'              => 'required|string|max:255|unique:orders,receipt_number',
        'saleDate'                   => 'required|date',
        'orderType'                  => 'required|in:deliver,walk_in',
        'paymentType'                => 'required|string',
        'paymentStatus'              => 'required|in:unpaid,paid,refunded',
        'discountPresetId'           => 'nullable|exists:discount_preset,id',
        'discountType'               => 'required|in:percentage,fixed,none',
        'discountValue'              => 'nullable|numeric|min:0',
        'status'                     => 'required|in:pending,preparing,in_transit,delivered,completed,cancelled',
        'selectedEmployeeId'         => 'nullable|exists:employees,id',
        'selectedCustomerId'         => 'nullable|exists:customers,id',
        'customerName'               => 'nullable|string|max:255',
        'customerUnit'               => 'nullable|string|max:255',
        'customerAddress'            => 'nullable|string|max:255',
        'customerContact'            => 'nullable|string|max:20',
        'orderItems'                 => 'required|array|min:1',
        'orderItems.*.product_id'    => 'required|exists:products,id',
        'orderItems.*.quantity'      => 'required|integer|min:1',
        'orderItems.*.price'         => 'required|numeric|min:0',
        'orderItems.*.discount'      => 'nullable|numeric|min:0',
        'proofOfPayment'             => 'nullable|image|mimes:png,jpg,jpeg,webp|max:10240',
    ];

    public function mount(): void
    {
        $this->receiptNumber = $this->generateReceiptNumber();
        $this->saleDate      = now()->format('Y-m-d\TH:i');
        $this->orderType     = config('storeconfig.default_order_type', 'walk_in');
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
            return '-P0';
        }

        $formattedAmount = 'P' . $this->formatMoneyCompact($amount);

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

    public function updatedOrderType($value): void
    {
        if ($value === 'walk_in') {
            $this->selectedEmployeeId    = null;
            $this->selectedCustomerId    = null;
            $this->isCreatingNewCustomer = false;
            $this->customerName = $this->customerUnit = $this->customerAddress = $this->customerContact = '';
            $this->resetErrorBag(['selectedEmployeeId', 'selectedCustomerId']);
            $this->dispatch('customer-validation-clear');
        }
    }

    public function updatedPaymentType(): void
    {
        // Only clear the proof when switching TO cash — switching between
        // non-cash methods should keep whatever was already uploaded.
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

    // ── Modal ──────────────────────────────────────────────────────

    public function openSaveConfirmation(): void
    {
        $this->syncDiscountFromSelection();

        if (! $this->validateSubmissionRequirements()) {
            $this->showConfirmModal = false;
            return;
        }

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
        $this->createOrder();
    }

    // ── Validation ─────────────────────────────────────────────────

    protected function getSubmissionRules(): array
    {
        $rules = $this->rules;
        // Validate paymentType against configured types
        $other = config('storeconfig.other_payment_types', []);
        $other = is_array($other) ? $other : array_filter(array_map('trim', explode(',', (string) $other)));
        $allowed = array_unique(array_merge(['cash'], array_values($other)));
        $rules['paymentType'] = 'required|in:' . implode(',', $allowed);

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

        if (! $this->validateSubmissionRequirements()) {
            return;
        }

        $hasItems = collect($this->orderItems)->some(fn ($i) => ! empty($i['product_id']));
        if (! $hasItems) {
            $fields = collect(array_keys($this->orderItems))
                ->map(fn ($i) => "orderItems.{$i}.product_id")
                ->values()->all();
            $this->dispatch('form-validation-failed', errorFields: $fields);
            return;
        }

        // Store proof image. Generalized from "=== 'gcash'" to "!== 'cash'"
        // so any configured non-cash method can attach proof and auto-upgrade
        // the payment status.
        $proofPath = null;
        if ($this->paymentType !== 'cash' && $this->proofOfPayment) {
            $ext       = strtolower($this->proofOfPayment->getClientOriginalExtension() ?: 'png');
            $dir       = 'order/' . $this->receiptNumber;
            $proofPath = $this->proofOfPayment->storeAs($dir, $this->receiptNumber . '.' . $ext, 'public');

            // Auto-upgrade to paid if proof uploaded
            if ($this->paymentStatus === 'unpaid') {
                $this->paymentStatus = 'paid';
            }
        }

        DB::transaction(function () use ($proofPath, $discountConfig, $finalTotal) {
            $customerId = $this->persistCustomer();

            $order = Order::create([
                'customer_id'      => $customerId,
                'created_by'       => Auth::id(),
                'delivered_by'     => $this->orderType === 'deliver' ? $this->selectedEmployeeId : null,
            'order_total'      => $finalTotal,
            'discount_preset_id' => $discountConfig['type'] === 'none' ? null : $discountConfig['preset_id'],
            'discount_type'    => $discountConfig['type'],
            'discount_value'   => $discountConfig['type'] === 'none' ? 0 : (float) $discountConfig['value'],
                'order_type'       => $this->orderType,
                'payment_type'     => $this->paymentType,
                'payment_status'   => $this->paymentStatus,
                'status'           => $this->status,
                'receipt_number'   => $this->receiptNumber,
                'proof_of_payment' => $proofPath,
            ]);

            $saleDate = Carbon::parse($this->saleDate);
            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ]);

            foreach ($this->orderItems as $item) {
                if (! ($item['product_id'] ?? null)) continue;

                $qty       = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = max(0, (float) ($item['price'] ?? 0));
                $discount  = min(max(0, (float) ($item['discount'] ?? 0)), $qty * $unitPrice);
                $lineTotal = max(0, ($qty * $unitPrice) - $discount);

                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => (int) $item['product_id'],
                    'quantity'    => $qty,
                    'unit_price'  => $unitPrice,
                    'discount_amount' => $discount,
                    'total_price' => $lineTotal,
                ]);
            }

            // ── Audit ──────────────────────────────────────────────────────
            // Use 'order.backdated' to distinguish manual sales records from live orders
            app(AuditLogsService::class)->record(
                'order.backdated',
                Auth::user(),
                $order,
                [],
                [
                    'receipt_number' => $order->receipt_number,
                    'order_type'     => $order->order_type,
                    'order_total'    => $order->order_total,
                    'payment_type'   => $order->payment_type,
                    'payment_status' => $order->payment_status,
                    'status'         => $order->status,
                    'sale_date'      => $this->saleDate,
                ],
                request()
            );
        });

        $this->resetFormAfterSave();
        $this->dispatch('show-success', ['message' => __('Sales record created successfully!')]);
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

    protected function resetFormAfterSave(): void
    {
        $this->receiptNumber         = $this->generateReceiptNumber();
        $this->saleDate              = now()->format('Y-m-d\TH:i');
        $this->orderType             = config('storeconfig.default_order_type', 'walk_in');
        $this->paymentType           = 'cash';
        $this->paymentStatus         = 'paid';
        $this->discountPresetId      = null;
        $this->discountType          = 'none';
        $this->discountValue         = 0;
        $this->status                = 'completed';
        $this->selectedEmployeeId    = null;
        $this->selectedCustomerId    = null;
        $this->isCreatingNewCustomer = false;
        $this->customerName          = '';
        $this->customerUnit          = '';
        $this->customerAddress       = '';
        $this->customerContact       = '';
        $this->customerSearch        = '';
        $this->employeeSearch        = '';
        $this->productSearch         = '';
        $this->orderItems            = [];
        $this->showConfirmModal      = false;
        $this->errorFields           = [];
        $this->proofOfPayment        = null;

        $this->resetProductForm();
        $this->addOrderItem();
        $this->resetErrorBag();
        $this->dispatch('customer-validation-clear');
    }

    public function render()
    {
        return view('livewire.order.add');
    }
}