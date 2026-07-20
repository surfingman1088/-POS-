<x-layouts.app>
    <div class="p-4 md:p-6 space-y-5">

        {{-- 頁面標題 --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">入庫管理</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">廠商進貨記錄與倉庫庫存更新</p>
            </div>
            <button wire:click="$set('showForm', true)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-plus class="w-4 h-4" />
                新增入庫單
            </button>
        </div>

        {{-- 新增入庫單表單 --}}
        @if($showForm)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-o-arrow-down-tray class="w-5 h-5 text-green-500" />
                新增入庫單
            </h2>

            {{-- 表頭資訊 --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">入庫日期 <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="receiptDate"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                    @error('receiptDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">廠商名稱</label>
                    <input type="text" wire:model="supplierName" placeholder="廠商名稱"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">批次號碼</label>
                    <input type="text" wire:model="batchNo" placeholder="批次號碼（選填）"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">備註</label>
                <textarea wire:model="notes" rows="2" placeholder="備註（選填）"
                          class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
            </div>

            {{-- 入庫明細 --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">入庫明細</h3>
                    <button wire:click="addItem" type="button"
                            class="inline-flex items-center gap-1 text-xs text-green-600 hover:text-green-700 font-medium">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        新增一行
                    </button>
                </div>

                <div class="space-y-2">
                    @foreach($receiptItems as $index => $item)
                    <div class="grid grid-cols-12 gap-2 items-start">
                        {{-- 商品 --}}
                        <div class="col-span-4">
                            <select wire:model.live="receiptItems.{{ $index }}.product_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">-- 選擇商品 --</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            @error("receiptItems.{$index}.product_id") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        {{-- 規格 --}}
                        <div class="col-span-3">
                            <select wire:model="receiptItems.{{ $index }}.variant_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    {{ empty($item['variants']) ? 'disabled' : '' }}>
                                <option value="">{{ empty($item['variants']) ? '無規格' : '-- 選擇規格 --' }}</option>
                                @foreach($item['variants'] as $variant)
                                <option value="{{ $variant['id'] }}">{{ $variant['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 數量 --}}
                        <div class="col-span-2">
                            <input type="number" wire:model="receiptItems.{{ $index }}.quantity" min="1"
                                   placeholder="數量"
                                   class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                            @error("receiptItems.{$index}.quantity") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        {{-- 進貨單價 --}}
                        <div class="col-span-2">
                            <input type="number" wire:model="receiptItems.{{ $index }}.unit_cost" min="0" step="0.01"
                                   placeholder="進貨單價"
                                   class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
                        </div>
                        {{-- 刪除 --}}
                        <div class="col-span-1 flex justify-center pt-2">
                            <button wire:click="removeItem({{ $index }})" type="button"
                                    class="text-red-400 hover:text-red-600 transition">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- 操作按鈕 --}}
            <div class="flex items-center gap-3 pt-2 border-t border-gray-200 dark:border-zinc-700">
                <button wire:click="saveReceipt" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                    <span wire:loading wire:target="saveReceipt" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    確認入庫
                </button>
                <button wire:click="resetForm" type="button"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">
                    取消
                </button>
            </div>
        </div>
        @endif

        {{-- 搜尋 --}}
        <div class="flex items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜尋入庫單號或廠商..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent" />
            </div>
        </div>

        {{-- 入庫單列表 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">入庫單號</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">廠商</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">入庫日期</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">品項數</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">總數量</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">建立人</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">建立時間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($receipts as $receipt)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-green-600 dark:text-green-400">{{ $receipt->receipt_no }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $receipt->supplier_name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $receipt->receipt_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">{{ $receipt->items->count() }}</td>
                            <td class="px-4 py-3 text-center font-medium text-green-600 dark:text-green-400">+{{ $receipt->items->sum('quantity') }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ $receipt->creator->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $receipt->created_at->format('m/d H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">尚無入庫記錄</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($receipts->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $receipts->links() }}
            </div>
            @endif
        </div>

    </div>
</x-layouts.app>
