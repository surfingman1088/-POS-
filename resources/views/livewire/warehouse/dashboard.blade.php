<x-layouts.app>
    <div class="p-4 md:p-6 space-y-6">

        {{-- 頁面標題 --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('倉儲管理') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('中央倉庫庫存概覽與各分店狀態') }}</p>
            </div>
            <span class="text-sm text-gray-400">{{ now()->format('Y-m-d H:i') }}</span>
        </div>

        {{-- 統計卡片 --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {{-- 倉庫商品種類 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <x-heroicon-o-archive-box class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">倉庫品項</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $totalWarehouseItems }}</p>
                    </div>
                </div>
            </div>

            {{-- 低庫存 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-yellow-200 dark:border-yellow-800 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">低庫存</p>
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $lowStockCount }}</p>
                    </div>
                </div>
            </div>

            {{-- 缺貨 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-red-200 dark:border-red-800 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">缺貨</p>
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $outOfStockCount }}</p>
                    </div>
                </div>
            </div>

            {{-- 本月入庫 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-green-200 dark:border-green-800 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <x-heroicon-o-arrow-down-tray class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">本月入庫</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $monthlyReceipts }}</p>
                    </div>
                </div>
            </div>

            {{-- 本月出庫 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-orange-200 dark:border-orange-800 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">本月出庫</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $monthlyDispatches }}</p>
                    </div>
                </div>
            </div>

            {{-- 待確認盤點 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-purple-200 dark:border-purple-800 p-4 col-span-1">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">待確認盤點</p>
                        <p class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ $pendingStocktakes }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- 快速操作按鈕 --}}
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('warehouse.receipt') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                新增入庫單
            </a>
            <a href="{{ route('warehouse.dispatch') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                新增出庫單
            </a>
            <a href="{{ route('warehouse.stocktake') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-clipboard-document-check class="w-4 h-4" />
                新增盤點
            </a>
            <a href="{{ route('warehouse.branch-stock') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                <x-heroicon-o-building-storefront class="w-4 h-4" />
                分店庫存查詢
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- 低庫存警示 --}}
            @if($lowStockItems->isNotEmpty() || $outOfStockItems->isNotEmpty())
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-zinc-700 flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-yellow-500" />
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">庫存警示</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-700 max-h-72 overflow-y-auto">
                    @foreach($outOfStockItems as $item)
                    <div class="px-4 py-2.5 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $item->product->name }}
                                @if($item->variant) <span class="text-gray-500">・{{ $item->variant->name }}</span> @endif
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            缺貨
                        </span>
                    </div>
                    @endforeach
                    @foreach($lowStockItems as $item)
                    <div class="px-4 py-2.5 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $item->product->name }}
                                @if($item->variant) <span class="text-gray-500">・{{ $item->variant->name }}</span> @endif
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                            剩 {{ $item->quantity }} 件
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 各分店庫存概覽 --}}
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-zinc-700 flex items-center gap-2">
                    <x-heroicon-o-building-storefront class="w-4 h-4 text-blue-500" />
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">各分店庫存概覽</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach($branchStockSummary as $summary)
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                                <span class="text-xs font-bold text-orange-600 dark:text-orange-400">
                                    {{ mb_substr($summary['branch']->name, 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $summary['branch']->name }}</p>
                                <p class="text-xs text-gray-500">{{ $summary['items'] }} 種商品</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_qty']) }}</p>
                            <p class="text-xs text-gray-500">件庫存</p>
                        </div>
                    </div>
                    @endforeach
                    @if(empty($branchStockSummary))
                    <div class="px-4 py-8 text-center text-sm text-gray-500">尚無分店庫存資料</div>
                    @endif
                </div>
            </div>

        </div>

        {{-- 最近異動記錄 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-clock class="w-4 h-4 text-gray-500" />
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">最近異動記錄</h3>
                </div>
                <a href="{{ route('warehouse.movements') }}" wire:navigate class="text-xs text-orange-500 hover:text-orange-600">查看全部 →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">時間</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">商品</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">類型</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">數量</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">操作人</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($recentMovements as $mv)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">{{ $mv->created_at->format('m/d H:i') }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white">
                                {{ $mv->product->name ?? '-' }}
                                @if($mv->variant) <span class="text-gray-500 text-xs">・{{ $mv->variant->name }}</span> @endif
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $typeColors = ['receipt' => 'green', 'dispatch' => 'orange', 'stocktake_adjust' => 'purple', 'manual' => 'blue'];
                                    $color = $typeColors[$mv->type] ?? 'gray';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700 dark:bg-{{ $color }}-900/30 dark:text-{{ $color }}-400">
                                    {{ $mv->type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right font-medium {{ $mv->quantity > 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $mv->quantity > 0 ? '+' : '' }}{{ $mv->quantity }}
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $mv->user->name ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">尚無異動記錄</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts.app>
