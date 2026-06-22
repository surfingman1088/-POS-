<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden"
    wire:loading.class="opacity-50 pointer-events-none"
    wire:target="categoryFilter,stockFilter,search,sortByField"
    wire:key="mobile-products-{{ $categoryFilter }}-{{ $stockFilter }}-{{ $search }}">

@forelse($products as $product)
    @php
        $isOutOfStock = empty($product->stocks) || (int)$product->stocks === 0;
        $catLabel = $categoryNames[$product->category] ?? __('Uncategorized');

        // Same status → color mapping drives the mobile top strip
        // AND the desktop inset accent, so both read identically.
        $statusColor = $isOutOfStock ? '#f87171'
            : ($product->stock_status === 'low_stock' ? '#fbbf24'
            : ($product->is_in_stock ? '#22c55e' : '#fb923c'));
        $strip = $isOutOfStock ? 'bg-red-400'
            : ($product->stock_status === 'low_stock' ? 'bg-yellow-400'
            : ($product->is_in_stock ? 'bg-green-500' : 'bg-orange-400'));
        $productColor = $product->color ?? null;
    @endphp

    <div wire:key="mobile-card-{{ $product->id }}"
        class="rounded-2xl border shadow-sm overflow-hidden transition-all hover:brightness-95 bg-gray-200 dark:bg-zinc-800
            {{ $isOutOfStock
                ? 'border-red-200 dark:border-red-900/50'
                : 'border-zinc-100 dark:border-zinc-700' }}"
        style="--product-color: {{ $productColor }};">

        {{-- Status strip --}}
        <div class="h-1 w-full {{ $strip }}"></div>

        <div class="p-4 space-y-3">
            {{-- Image + Name + ID + Category --}}
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-2.5 min-w-0">
                    <div class="w-10 h-10 rounded-xl overflow-hidden shrink-0 border border-zinc-100 dark:border-zinc-700 bg-white/60 dark:bg-zinc-900/40 flex items-center justify-center">
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-box text-zinc-300 dark:text-zinc-500"></i>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-(--product-color) truncate">{{ $product->name }}</p>
                        <p class="font-semibold text-xs text-(--product-color)/70 mt-0.5">
                            <span class="mr-1">ID: </span>
                            {{ $product->id }}
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium shrink-0
                            bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                    <i class="fas fa-tag text-[10px]"></i>{{ __($catLabel) }}
                </span>
            </div>

            {{-- Stats row --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-zinc-50 dark:bg-zinc-700/80 rounded-xl px-3 py-2 text-center">
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Price') }}</p>
                    <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($product->price, 2) }}</p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/80 rounded-xl px-3 py-2 text-center">
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Stock') }}</p>
                    <p class="text-sm font-bold {{ $isOutOfStock ? 'text-red-600 dark:text-red-400' : ($product->stock_status === 'low_stock' ? 'text-yellow-600 dark:text-yellow-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                        {{ $product->stocks }}
                    </p>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/80 rounded-xl px-3 py-2 text-center">
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Sold') }}</p>
                    <p class="text-sm font-bold text-green-600 dark:text-green-400">{{ $product->sold }}</p>
                </div>
            </div>

            {{-- Status badge --}}
            <div>
                @if($isOutOfStock)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                        <i class="fas fa-times-circle"></i>{{ __('Out of Stock') }}
                    </span>
                @elseif(!$product->is_in_stock)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                        <i class="fas fa-ban"></i>{{ __('Hidden') }}
                    </span>
                @elseif($product->stock_status === 'low_stock')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                        <i class="fas fa-exclamation-triangle"></i>{{ __('Low Stock') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        <i class="fas fa-check-circle"></i>{{ __('In Stock') }}
                    </span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-700 flex-wrap">
                @if(auth()->check() && auth()->user()->isAdmin())
                    {{-- Availability toggle (admin only) --}}
                    @if($product->is_in_stock)
                        <button @click="openArchiveModal({{ $product->id }})"
                            class="prod-card-btn text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20">
                            <i class="fas fa-eye-slash"></i>{{ __('Hide') }}
                        </button>
                    @elseif($isOutOfStock)
                        <span class="prod-card-btn text-zinc-400 cursor-not-allowed opacity-60"
                                title="{{ __('This product is out of stock. Edit to add more stocks.') }}">
                            <i class="fas fa-ban"></i>{{ __('Out Of Stock') }}
                        </span>
                    @else
                        <button wire:click="makeAvailable({{ $product->id }})"
                            class="prod-card-btn text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/20">
                            <i class="fas fa-eye"></i>{{ __('Show') }}
                        </button>
                    @endif
                    {{-- Edit (admin only) --}}
                    <button @click="openEditModal({{ $product->id }})"
                        class="prod-card-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
                        <i class="fas fa-edit"></i>{{ __('Edit') }}
                    </button>
                    {{-- Delete (admin only) --}}
                    @if(($product->order_items_count ?? 0) === 0)
                        <button @click="openDeleteModal({{ $product->id }})"
                            class="prod-card-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 ml-auto">
                            <i class="fas fa-trash"></i>{{ __('Delete') }}
                        </button>
                    @else
                        <span class="prod-card-btn text-zinc-400 opacity-60 cursor-not-allowed ml-auto"
                                title="{{ __('Cannot delete - has ongoing order') }}">
                            <i class="fas fa-ban"></i>{{ __('Pending') }}
                        </span>
                    @endif
                @else
                    {{-- Staff: view only --}}
                    <span class="text-xs text-zinc-400 italic px-1">{{ __('View Only') }}</span>
                @endif
            </div>
        </div>
    </div>

@empty
    <div class="sm:col-span-2 flex flex-col items-center justify-center py-20 text-zinc-400 dark:text-zinc-500">
        <i class="fas fa-box-open text-5xl mb-4 opacity-40"></i>
        <p class="text-sm">{{ __('No products found.') }}</p>
        <p class="text-xs mt-1 opacity-70">{{ __('Try adjusting your search or filter criteria.') }}</p>
    </div>
@endforelse
</div>
