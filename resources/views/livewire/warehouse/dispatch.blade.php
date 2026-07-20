<x-layouts.app>
    <div class="p-4 md:p-6 space-y-5">

        {{-- 頁面標題 --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">出庫管理</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">撥貨到各分店，庫存自動同步</p>
            </div>
            <button wire:click="$set('showForm', true)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-plus class="w-4 h-4" />
                新增出庫單
            </button>
        </div>

        {{-- 新增出庫單表單 --}}
        @if($showForm)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-o-arrow-up-tray class="w-5 h-5 text-orange-500" />
                新增出庫單
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">目標分店 <span class="text-red-500">*</span></label>
                    <select wire:model="branchId"
                            class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="">-- 選擇分店 --</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branchId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">出庫日期 <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="dispatchDate"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                    @error('dispatchDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">備註</label>
                    <input type="text" wire:model="notes" placeholder="備註（選填）"
                           class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                </div>
            </div>

            {{-- 出庫明細 --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">出庫明細</h3>
                    <button wire:click="addItem" type="button"
                            class="inline-flex items-center gap-1 text-xs text-orange-600 hover:text-orange-700 font-medium">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        新增一行
                    </button>
                </div>

                <div class="space-y-2">
                    @foreach($dispatchItems as $index => $item)
                    <div class="grid grid-cols-12 gap-2 items-start">
                        <div class="col-span-4">
                            <select wire:model.live="dispatchItems.{{ $index }}.product_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">-- 選擇商品 --</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            @error("dispatchItems.{$index}.product_id") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-3">
                            <select wire:model.live="dispatchItems.{{ $index }}.variant_id"
                                    class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    {{ empty($item['variants']) ? 'disabled' : '' }}>
                                <option value="">{{ empty($item['variants']) ? '無規格' : '-- 選擇規格 --' }}</option>
                                @foreach($item['variants'] as $variant)
                                <option value="{{ $variant['id'] }}">{{ $variant['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <input type="number" wire:model="dispatchItems.{{ $index }}.quantity" min="1"
                                   placeholder="數量"
                                   class="w-full rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                            @error("dispatchItems.{$index}.quantity") <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        {{-- 倉庫可用庫存提示 --}}
                        <div class="col-span-2 flex items-center">
                            @if(isset($item['available_stock']) && $item['available_stock'] !== null)
                            <span class="text-xs {{ $item['available_stock'] > 0 ? 'text-green-600' : 'text-red-500' }}">
                                可用: {{ $item['available_stock'] }}
                            </span>
                            @endif
                        </div>
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

            <div class="flex items-center gap-3 pt-2 border-t border-gray-200 dark:border-zinc-700">
                <button wire:click="saveDispatch" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                    <span wire:loading wire:target="saveDispatch" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    確認出庫
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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜尋出庫單號..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
            </div>
        </div>

        {{-- 出庫單列表 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">出庫單號</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">目標分店</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">出庫日期</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">品項數</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">總數量</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">建立人</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">建立時間</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($dispatches as $dispatch)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs font-medium text-orange-600 dark:text-orange-400">{{ $dispatch->dispatch_no }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                    {{ $dispatch->branch->name ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $dispatch->dispatch_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">{{ $dispatch->items->count() }}</td>
                            <td class="px-4 py-3 text-center font-medium text-orange-600 dark:text-orange-400">{{ $dispatch->items->sum('quantity') }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">{{ $dispatch->creator->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $dispatch->created_at->format('m/d H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">尚無出庫記錄</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($dispatches->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $dispatches->links() }}
            </div>
            @endif
        </div>

    </div>
</x-layouts.app>
