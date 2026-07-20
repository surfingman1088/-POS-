<x-layouts.app>
    <div class="p-4 md:p-6 space-y-5">

        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">進出貨記錄</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">所有庫存異動的完整歷史記錄</p>
        </div>

        {{-- 篩選列 --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 max-w-xs">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜尋商品名稱..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
            </div>
            <select wire:model.live="filterType"
                    class="rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                <option value="">全部類型</option>
                <option value="receipt">入庫</option>
                <option value="dispatch">出庫</option>
                <option value="stocktake_adjust">盤點調整</option>
                <option value="manual">手動調整</option>
            </select>
            <input type="date" wire:model.live="dateFrom"
                   class="rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
            <span class="text-gray-500 text-sm">至</span>
            <input type="date" wire:model.live="dateTo"
                   class="rounded-lg border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
        </div>

        {{-- 記錄列表 --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">時間</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">商品</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">規格</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">類型</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">來源 → 目的</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">異動數量</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">異動前</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">異動後</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">操作人</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">備註</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                        @forelse($movements as $mv)
                        @php
                            $typeColors = ['receipt' => 'green', 'dispatch' => 'orange', 'stocktake_adjust' => 'purple', 'manual' => 'blue'];
                            $color = $typeColors[$mv->type] ?? 'gray';
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">{{ $mv->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ $mv->product->name ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $mv->variant->name ?? '-' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700 dark:bg-{{ $color }}-900/30 dark:text-{{ $color }}-400">
                                    {{ $mv->type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">
                                {{ $mv->source }}
                                @if($mv->destination) → {{ $mv->destination }} @endif
                            </td>
                            <td class="px-4 py-2.5 text-center font-bold {{ $mv->quantity > 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $mv->quantity > 0 ? '+' : '' }}{{ $mv->quantity }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-500">{{ $mv->before_quantity }}</td>
                            <td class="px-4 py-2.5 text-center font-medium text-gray-900 dark:text-white">{{ $mv->after_quantity }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $mv->user->name ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs max-w-xs truncate">{{ $mv->notes ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-gray-500">尚無異動記錄</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $movements->links() }}
            </div>
            @endif
        </div>

    </div>
</x-layouts.app>
