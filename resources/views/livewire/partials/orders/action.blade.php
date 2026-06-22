{{--
    Order action buttons.
    Rendered inside a CSS grid so each "slot" always occupies the same space,
    preventing layout shift when state changes.

    Props:
      $order  – Order model
      $style  – 'card' | 'table'  (controls CSS class applied to each button)
--}}

@php
    $btn   = $style === 'table' ? 'tbl-action-btn' : 'card-action-btn';
    $isWalkIn = $order->order_type === 'walk_in';
    $isAdmin  = auth()->check() && auth()->user()->isAdmin();

    $editLocked = in_array($order->status, (array) config('storeconfig.order_edit_lock_status')) && $order->payment_status !== 'unpaid';

    // Determine which primary action to show
    $primarySlot = null;

    if ($isWalkIn && $order->payment_status === 'unpaid') {
        $primarySlot = 'walkin:unpaid';
    } elseif ($isWalkIn && $order->status === 'completed' && $order->payment_status === 'paid') {
        $primarySlot = 'completed:paid';
    } elseif ($isWalkIn && $order->payment_status === 'paid') {
        $primarySlot = 'walkin:paid';
    } elseif (!$isWalkIn && $order->status === 'pending') {
        $deliveryStatus = $this->getDeliveryPersonStatus($order->id);
        $primarySlot = 'pending:' . $deliveryStatus;
    } elseif (!$isWalkIn && $order->status === 'preparing') {
        $employeeId    = $order->delivered_by;
        $batchInfo     = $this->getBatchInfo($employeeId);
        $remainingTime = $batchInfo['remaining_time'] ?? 0;
        $primarySlot   = 'preparing';
    } elseif (!$isWalkIn && $order->status === 'in_transit') {
        $primarySlot = 'in_transit';
    } elseif ($order->status === 'delivered' && $order->payment_status === 'unpaid' || $order->payment_status === 'unpaid') {
        $primarySlot = 'delivered:unpaid';
    } elseif ($order->status === 'delivered' && $order->payment_status === 'paid') {
        $primarySlot = 'delivered:paid';
    } elseif ($order->status === 'completed' && $order->payment_status === 'paid') {
        $primarySlot = 'completed:paid';
    }
    // completed+refunded / cancelled → no primary action
@endphp

<div class="flex items-center {{ $style === 'table' ? 'justify-center' : '' }} gap-1 {{ $style === 'card' ? 'pt-1 border-t border-zinc-100 dark:border-zinc-700 flex-wrap' : '' }}">

    {{-- SLOT 1: View (all roles) --}}
    <button wire:click="viewOrderDetails({{ $order->id }})" class="{{ $btn }} text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
        <i class="fas fa-eye {{ $style === 'table' ? 'text-base' : '' }}"></i>
        <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('View') }}</span>
    </button>

    {{-- SLOT 2: Edit (admin only) --}}
    @if ($isAdmin)
        @if (!$editLocked)
            <a href="{{ route('orders.edit', $order) }}" wire:navigate class="{{ $btn }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700">
                <i class="fas fa-edit {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Edit') }}</span>
            </a>
        @else
            <span class="{{ $btn }} text-zinc-500 cursor-not-allowed opacity-50" title="{{ __('Cannot edit') }} — {{ $order->status }}">
                <i class="fas fa-lock {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Locked') }}</span>
            </span>
        @endif
    @else
        {{-- Staff: show locked placeholder --}}
        <span class="{{ $btn }} invisible" aria-hidden="true">
            <i class="fas fa-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
            <span class="{{ $style === 'table' ? 'text-xs' : '' }}">‌</span>
        </span>
    @endif

    {{-- SLOT 3: Primary action (admin only for paid/refund/deliver; staff sees status only) --}}
    <div class="inline-flex items-center justify-center min-w-20">

        @if ($isAdmin)

            @if ($primarySlot === 'pending:no_person')
                <span class="{{ $btn }} text-zinc-400 opacity-50 cursor-not-allowed">
                    <i class="fas fa-user-slash {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('No Staff') }}</span>
                </span>

            @elseif ($primarySlot === 'pending:available')
                <button wire:click="startDelivery({{ $order->id }})"
                    class="{{ $btn }} text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/20">
                    <i class="fas fa-truck {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Deliver') }}</span>
                </button>

            @elseif ($primarySlot === 'pending:batch_preparing' || $primarySlot === 'pending:busy')
                <button wire:click="startDelivery({{ $order->id }})"
                    class="{{ $btn }} text-yellow-600 hover:bg-yellow-50 dark:text-yellow-400 dark:hover:bg-yellow-900/20">
                    <i class="fas fa-plus-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Add Delivery') }}</span>
                </button>

            @elseif ($primarySlot === 'pending:preparing')
                <span class="{{ $btn }} text-yellow-600 dark:text-yellow-400">
                    <i class="fas fa-hourglass-half {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('In Batch') }}</span>
                </span>

            @elseif ($primarySlot === 'pending:waiting')
                <span class="{{ $btn }} text-purple-600 dark:text-purple-400 opacity-75">
                    <i class="fas fa-clock-rotate-left {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('In Queue') }}</span>
                </span>

            @elseif (str_starts_with((string)$primarySlot, 'pending:'))
                <span class="{{ $btn }} text-orange-600 dark:text-orange-400 opacity-75">
                    <i class="fas fa-clock {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Busy') }}</span>
                </span>

            @elseif ($primarySlot === 'preparing')
                @if ($order->delivered_by)
                    <div x-data="{
                            r: {{ $remainingTime }},
                            started: false,
                            tick() {
                                if (this.started) return;
                                this.started = true;
                                let iv = setInterval(() => {
                                    if (this.r > 0) { this.r--; }
                                    else { clearInterval(iv); $wire.processBatchDelivery({{ $order->delivered_by }}); }
                                }, 1000);
                            }
                        }"
                        x-init="tick()"
                        class="{{ $btn }} text-yellow-600 dark:text-yellow-400">
                        <i class="fas fa-hourglass-half {{ $style === 'table' ? 'text-base' : '' }}"></i>
                        <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Preparing') }}</span>
                        <span class="font-mono text-[10px] bg-yellow-100 dark:bg-yellow-900/30 px-1 rounded ml-1" x-text="Math.floor(r/60)+':'+String(r%60).padStart(2,'0')"></span>
                    </div>
                @endif

            @elseif ($primarySlot === 'in_transit')
                <button wire:click="markDelivered({{ $order->id }})"
                    class="{{ $btn }} text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/20">
                    <i class="fas fa-box-open {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Delivered') }}</span>
                </button>

            @elseif ($primarySlot === 'walkin:unpaid' || $primarySlot === 'delivered:unpaid')
                <button wire:click="togglePaid({{ $order->id }})"
                    class="{{ $btn }} text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
                    <i class="fas fa-money-bill-transfer {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Paid') }}</span>
                </button>

            @elseif ($primarySlot === 'walkin:paid' || $primarySlot === 'delivered:paid')
                <button wire:click="markFinished({{ $order->id }})"
                    class="{{ $btn }} text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20">
                    <i class="fas fa-check-double {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Complete') }}</span>
                </button>

            @elseif ($primarySlot === 'completed:paid')
                <button type="button"
                    x-data
                    @click="$dispatch('open-refund', { orderId: {{ $order->id }} })"
                    class="{{ $btn }} text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20">
                    <i class="fas fa-rotate-left {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Refund') }}</span>
                </button>

            @else
                <span class="{{ $btn }} invisible" aria-hidden="true">
                    <i class="fas fa-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
                    <span class="{{ $style === 'table' ? 'text-xs' : '' }}">‌</span>
                </span>
            @endif

        @else
            {{-- Staff: show empty placeholder for primary slot --}}
            <span class="{{ $btn }} invisible" aria-hidden="true">
                <i class="fas fa-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">‌</span>
            </span>
        @endif

    </div>

    {{-- SLOT 4: Cancel | Delete (admin only) --}}
    @if ($isAdmin)
        @if($order->status === 'preparing')
            <button wire:click="cancelPrepare({{ $order->id }})" class="{{ $btn }} text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20 {{ $style === 'card' ? 'ml-auto' : '' }}">
                <i class="fas fa-hand {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Stop') }}</span>
            </button>

        @elseif (!in_array($order->status, ['cancelled', 'completed', 'preparing', 'delivered'], true))
            <button wire:click="openCancel({{ $order->id }})" class="{{ $btn }} text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20 {{ $style === 'card' ? 'ml-auto' : '' }}">
                <i class="fas fa-ban {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Cancel') }}</span>
            </button>

        @elseif (in_array($order->status, ['cancelled', 'completed', 'delivered'], true))
            <button wire:click="confirmDelete({{ $order->id }})" class="{{ $btn }} text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 {{ $style === 'card' ? 'ml-auto' : '' }}">
                <i class="fas fa-trash {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">{{ __('Delete') }}</span>
            </button>

        @else
            <span class="{{ $btn }} invisible" aria-hidden="true">
                <i class="fas fa-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
                <span class="{{ $style === 'table' ? 'text-xs' : '' }}">‌</span>
            </span>
        @endif
    @else
        {{-- Staff: hide cancel/delete slot --}}
        <span class="{{ $btn }} invisible" aria-hidden="true">
            <i class="fas fa-circle {{ $style === 'table' ? 'text-base' : '' }}"></i>
            <span class="{{ $style === 'table' ? 'text-xs' : '' }}">‌</span>
        </span>
    @endif

</div>
