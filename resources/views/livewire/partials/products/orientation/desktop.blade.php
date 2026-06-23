<div class="hidden lg:block bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden"
    wire:key="desktop-products-{{ $categoryFilter }}-{{ $stockFilter }}-{{ $search }}">

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700"
                wire:loading.class="opacity-40 pointer-events-none"
                wire:target="categoryFilter,stockFilter,search,sortByField">

            <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                <tr>
                    {{-- Image --}}
                    <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Image') }}
                    </th>

                    {{-- Product name --}}
                    <th wire:click="sortByField('name')"
                        class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                        <div class="flex items-center gap-1">
                            {{ __('Product Name') }}
                            @if($sortBy === 'name')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                            @else
                                <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                            @endif
                        </div>
                    </th>

                    {{-- Category --}}
                    <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Category') }}
                    </th>

                    {{-- Price --}}
                    <th wire:click="sortByField('price')"
                        class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                        <div class="flex items-center justify-center gap-1">
                            {{ __('Price') }}
                            @if($sortBy === 'price')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                            @else
                                <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                            @endif
                        </div>
                    </th>

                    {{-- Products Sold --}}
                    <th wire:click="sortByField('sold')"
                        class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                        <div class="flex items-center justify-center gap-1">
                            {{ __('Sold') }}
                            @if($sortBy === 'sold')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                            @else
                                <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                            @endif
                        </div>
                    </th>

                    {{-- Actions --}}
                    <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse($products as $index => $product)
                    @php
                        $isOutOfStock  = empty($product->stocks) || (int)$product->stocks === 0;
                        $catLabel      = $categoryNames[$product->category] ?? __('Uncategorized');
                        $statusColor   = $isOutOfStock ? '#f87171'
                            : ($product->stock_status === 'low_stock' ? '#fbbf24'
                            : ($product->is_in_stock ? '#22c55e' : '#fb923c'));
                        $productColor  = $product->color ?? null;
                    @endphp
                    <tr wire:key="product-row-{{ $product->id }}-{{ $categoryFilter }}-{{ $stockFilter }}-{{ $search }}-{{ $index }}"
                        class="transition hover:brightness-95 {{ $productColor ? 'bg-(--row-color)/10' : 'bg-gray-200 dark:bg-zinc-800' }}"
                        style="
                            --row-color: {{ $statusColor }};
                            --product-color: {{ $productColor }};
                            box-shadow: inset 4px 0 0 0 {{ $statusColor }};
                        "
                    >

                        {{-- Image --}}
                        <td class="px-4 py-3">
                            <div class="w-28 h-28 rounded-xl overflow-hidden border border-zinc-100 dark:border-zinc-700 bg-white/60 dark:bg-zinc-900/40 flex items-center justify-center mx-auto">
                                @if($product->image_url)
                                    <img src="{{ $product->image_url }}" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-box text-zinc-300 dark:text-zinc-500"></i>
                                @endif
                            </div>
                        </td>

                        {{-- Name + ID --}}
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-(--product-color)">{{ $product->name }}</div>
                            <div class="text-xs font-semibold text-(--product-color)/70 mt-0.5">
                                <span class="opacity-50">ID:</span>
                                {{ $product->id }}
                            </div>
                        </td>

                        {{-- Category --}}
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium
                                            bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                <i class="fas fa-tag text-[10px]"></i>{{ __($catLabel) }}
                            </span>
                        </td>

                        {{-- Price --}}
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">₱{{ number_format($product->price, 2) }}</span>
                        </td>

                        {{-- Sold --}}
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                <i class="fas fa-chart-bar mr-1 opacity-70"></i>{{ $product->sold }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                @if(auth()->check() && auth()->user()->isAdmin())
                                    {{-- Availability toggle (admin only) --}}
                                    @if($product->is_in_stock)
                                        <button @click="openArchiveModal({{ $product->id }})"
                                            class="tbl-action-btn text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20"
                                            title="{{ __('This Item is Currently for Sale - Mark as Unavailable') }}">
                                            <i class="fas fa-eye-slash text-sm"></i>
                                            <span class="text-xs">{{ __('Hide') }}</span>
                                        </button>
                                    @elseif($isOutOfStock)
                                        <span class="tbl-action-btn text-zinc-400 opacity-50 cursor-not-allowed"
                                                title="{{ __('This product is out of stock. Edit to add more stocks.') }}">
                                            <i class="fas fa-ban text-sm"></i>
                                            <span class="text-xs leading-tight">{{ __('Out of Stock') }}</span>
                                        </span>
                                    @else
                                        <button wire:click="makeAvailable({{ $product->id }})"
                                            class="tbl-action-btn text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20"
                                            title="{{ __('This Item is Currently Hidden - Make Available for Sale') }}">
                                            <i class="fas fa-eye text-sm"></i>
                                            <span class="text-xs">{{ __('Show') }}</span>
                                        </button>
                                    @endif
                                    {{-- Edit (admin only) --}}
                                    <button @click="openEditModal({{ $product->id }})"
                                        class="tbl-action-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                                        title="{{ __('Edit Product') }}">
                                        <i class="fas fa-edit text-sm"></i>
                                        <span class="text-xs">{{ __('Edit') }}</span>
                                    </button>
                                    {{-- Delete (admin only) --}}
                                    @if(($product->order_items_count ?? 0) === 0)
                                        <button @click="openDeleteModal({{ $product->id }})"
                                            class="tbl-action-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                            title="{{ __('Delete Permanently. This action cannot be undone.') }}">
                                            <i class="fas fa-trash text-sm"></i>
                                            <span class="text-xs">{{ __('Delete') }}</span>
                                        </button>
                                    @else
                                        <span class="tbl-action-btn text-zinc-400 opacity-50 cursor-not-allowed"
                                                title="{{ __('Cannot delete - has ongoing order') }}">
                                            <i class="fas fa-ban text-sm"></i>
                                            <span class="text-xs">{{ __('Pending') }}</span>
                                        </span>
                                    @endif
                                @else
                                    {{-- Staff: 入庫按鈕 --}}
                                    <button wire:click="openRestockModal({{ $product->id }})"
                                        class="tbl-action-btn text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                        title="{{ __('Add Stock') }}">
                                        <i class="fas fa-plus-circle text-sm"></i>
                                        <span class="text-xs">{{ __('Restock') }}</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr wire:key="no-products-{{ $categoryFilter }}-{{ $stockFilter }}-{{ $search }}">
                        <td colspan="6" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center text-zinc-400 dark:text-zinc-500">
                                <i class="fas fa-box-open text-5xl mb-4 opacity-40"></i>
                                <p class="text-sm font-medium">{{ __('No products found.') }}</p>
                                <p class="text-xs mt-1 opacity-70">{{ __('Try adjusting your search or filter criteria.') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Desktop pagination --}}
    <div class="hidden lg:block px-4 py-3 border-t border-zinc-100 dark:border-zinc-700">
        @if($products->hasPages())
            {{ $products->links() }}
        @endif
    </div>
</div>
