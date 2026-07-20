<x-layouts.app>
    <div class="p-4 md:p-6 space-y-5">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">庫存查詢</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">即時查詢各分店與中央倉庫庫存</p>
            </div>
        </div>

        {{-- 篩選列 --}}
        <div class="flex flex-wrap items-center gap-3">
            {{-- 檢視模式 --}}
            <div class="flex rounded-lg border border-gray-300 dark:border-zinc-600 overflow-hidden">
                <button wire:click="$set('viewMode', 'branch')"
                        class="px-4 py-2 text-sm font-medium transition {{ $viewMode === 'branch' ? 'bg-orange-500 text-white' : 'bg-white dark:bg-zinc-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-600' }}">
                    分店庫存
                </button>
                <button wire:click="$set('viewMode', 'warehouse')"
                        class="px-4 py-2 text-sm font-medium transition {{ $viewMode === 'warehouse' ? 'bg-orange-500 text-white' : 'bg-white dark:bg-zinc-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-600' }}">
                    中央倉庫
                </button>
            </div>

            {{-- 分店選擇（分店模式才顯示） --}}
            @if($viewMode === 'branch')
            <select wire:model.live="selectedBranch"
                    class="rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                <option value="">全部分店</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            @endif

            {{-- 搜尋 --}}
            <div class="relative flex-1 max-w-sm">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜尋商品名稱..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
            </div>
        </div>

        {{-- 庫存列表 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            @if($viewMode === 'branch')
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">分店</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">商品名稱</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">規格</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">分類</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">庫存數量</th>
                            @if($viewMode === 'warehouse')
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">低庫存門檻</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">狀態</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($stocks as $stock)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30 {{ $stock->quantity <= 0 ? 'opacity-60' : '' }}">
                            @if($viewMode === 'branch')
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                    {{ $stock->branch->name ?? '-' }}
                                </span>
                            </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white {{ $stock->quantity <= 0 ? 'font-bold' : '' }}">
                                {{ $stock->product->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $stock->variant->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $stock->product->categoryRecord->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold text-base {{ $stock->quantity > 0 ? 'text-gray-900 dark:text-white' : 'text-red-500' }}">
                                    {{ $stock->quantity }}
                                </span>
                            </td>
                            @if($viewMode === 'warehouse')
                            <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $stock->low_stock_threshold }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($stock->quantity <= 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">缺貨</span>
                                @elseif($stock->quantity <= $stock->low_stock_threshold)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">低庫存</span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">正常</span>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">尚無庫存資料</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($stocks->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $stocks->links() }}
            </div>
            @endif
        </div>

    </div>
</x-layouts.app>
