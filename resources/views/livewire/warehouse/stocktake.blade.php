<x-layouts.app>
    <div class="p-4 md:p-6 space-y-5">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">庫存盤點</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">實際庫存 vs 系統庫存，確認後自動套用差異</p>
            </div>
            <button wire:click="$set('showForm', true)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-plus class="w-4 h-4" />
                新增盤點
            </button>
        </div>

        {{-- 新增盤點表單 --}}
        @if($showForm)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-purple-500" />
                新增盤點單
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">盤點類型</label>
                    <select wire:model.live="type"
                            class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="warehouse">中央倉庫</option>
                        <option value="branch">分店</option>
                    </select>
                </div>
                @if($type === 'branch')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">盤點分店 <span class="text-red-500">*</span></label>
                    <select wire:model.live="branchId"
                            class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">-- 選擇分店 --</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branchId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">盤點日期 <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="stocktakeDate"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
                </div>
                <div class="flex items-end">
                    <button wire:click="loadAllProducts" type="button"
                            class="w-full px-4 py-2 bg-gray-100 dark:bg-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition">
                        載入所有商品
                    </button>
                </div>
            </div>

            {{-- 盤點明細表格 --}}
            @if(!empty($stocktakeItems))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">商品名稱</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">規格</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">系統庫存</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">實際數量</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">差異</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">備註</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @foreach($stocktakeItems as $index => $item)
                        @php $diff = (int)$item['actual_quantity'] - (int)$item['system_quantity']; @endphp
                        <tr class="{{ $diff !== 0 ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $item['product_name'] }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ $item['variant_name'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $item['system_quantity'] }}</td>
                            <td class="px-3 py-2">
                                <input type="number" wire:model.live="stocktakeItems.{{ $index }}.actual_quantity" min="0"
                                       class="w-20 mx-auto block rounded border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-2 py-1 text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
                            </td>
                            <td class="px-3 py-2 text-center font-medium {{ $diff > 0 ? 'text-green-600' : ($diff < 0 ? 'text-red-500' : 'text-gray-400') }}">
                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" wire:model="stocktakeItems.{{ $index }}.notes" placeholder="備註"
                                       class="w-full rounded border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-2 py-1 text-xs focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8 text-gray-500 text-sm">請點擊「載入所有商品」開始盤點</div>
            @endif

            <div class="flex items-center gap-3 pt-2 border-t border-gray-200 dark:border-zinc-700">
                <button wire:click="saveStocktake" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                    <span wire:loading wire:target="saveStocktake" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    儲存盤點單（草稿）
                </button>
                <button wire:click="resetForm" type="button"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">
                    取消
                </button>
            </div>
        </div>
        @endif

        {{-- 確認盤點 Modal --}}
        @if($showConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-zinc-800 rounded-xl p-6 max-w-sm w-full mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">確認套用盤點差異？</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">確認後，庫存將根據實際盤點數量調整，此操作無法復原。</p>
                <div class="flex gap-3">
                    <button wire:click="confirmStocktake"
                            class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                        確認套用
                    </button>
                    <button wire:click="$set('showConfirmModal', false)"
                            class="flex-1 px-4 py-2 bg-gray-100 dark:bg-zinc-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition">
                        取消
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- 搜尋 --}}
        <div class="relative max-w-sm">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜尋盤點單號..."
                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
        </div>

        {{-- 盤點單列表 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">盤點單號</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">類型</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">盤點日期</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">品項數</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">狀態</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">建立人</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($stocktakes as $stocktake)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-purple-600 dark:text-purple-400">{{ $stocktake->stocktake_no }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ $stocktake->type === 'warehouse' ? '中央倉庫' : ($stocktake->branch->name ?? '分店') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $stocktake->stocktake_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">{{ $stocktake->items->count() }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($stocktake->status === 'confirmed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">已確認</span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">草稿</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ $stocktake->creator->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($stocktake->status === 'draft')
                                <button wire:click="openConfirm({{ $stocktake->id }})"
                                        class="text-xs px-3 py-1 bg-purple-100 hover:bg-purple-200 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 rounded-lg transition">
                                    確認套用
                                </button>
                                @else
                                <span class="text-xs text-gray-400">{{ $stocktake->confirmed_at?->format('m/d H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">尚無盤點記錄</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($stocktakes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $stocktakes->links() }}
            </div>
            @endif
        </div>

    </div>
</x-layouts.app>
