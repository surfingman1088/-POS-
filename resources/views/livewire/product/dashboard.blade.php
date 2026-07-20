@section('title', __('Product Inventory'))

<div class="w-full max-w-full overflow-x-hidden px-2 sm:px-4 pb-8"
    x-data="{
        showFormModal: false,
        formMode: 'create',
        showArchiveModal: false,
        showDeleteModal: false,
        editLoading: false,

        openCreateModal() {
            this.formMode = 'create';
            $wire.openCreateModal();
            this.showFormModal = true;
        },
        openEditModal(id) {
            this.formMode = 'edit';
            this.editLoading = true;
            this.showFormModal = true;
            $wire.openEditModal(id);
        },
        closeFormModal() {
            this.showFormModal = false;
            this.editLoading = false;
            $wire.resetForm();
        },

        openDeleteModal(id) {
            $wire.selectedProductId = id;
            this.showDeleteModal = true;
        },
        closeDeleteModal() {
            this.showDeleteModal = false;
            $wire.resetForm();
        },

        openArchiveModal(id) {
            $wire.selectedProductId = id;
            this.showArchiveModal = true;
        },
        closeArchiveModal() {
            this.showArchiveModal = false;
            $wire.selectedProductId = null;
        },
    }"

    @close-form-modal.window="closeFormModal()"
    @close-delete-modal.window="closeDeleteModal()"
    @close-archive-modal.window="closeArchiveModal()"
    @edit-product-loaded.window="editLoading = false"
    >

    {{-- HEADER --}}
    <div class="flex items-start justify-between gap-3 py-2 mb-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <i class="fas fa-boxes text-blue-500"></i>
                {{ __('Product Inventory') }}
            </h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                {{ __('Manage your product inventory and stock levels') }}
            </p>
        </div>
        <button @click="openCreateModal()"
            class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold
                   hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20 shrink-0">
            <i class="fas fa-plus"></i>
            <span>{{ __('Add Product') }}</span>
        </button>
    </div>

    {{-- QUICK STATS  (2×2 on mobile, 4-col on md+) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">

        {{-- Total --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-box text-blue-600 dark:text-blue-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">{{ $products->total() }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Total Products') }}</div>
            </div>
        </div>

        {{-- In Stock --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                    {{ $allProducts->where('stocks', '>=', 10)->count() }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('In Stock') }}</div>
            </div>
        </div>

        {{-- Low Stock --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-yellow-100 dark:bg-yellow-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                    {{ $allProducts->where('stocks', '<', 10)->where('stocks', '>', 0)->count() }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Low Stock') }}</div>
            </div>
        </div>

        {{-- Out of Stock --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-times-circle text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                    {{ $allProducts->where('stocks', '==', 0)->count() }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Out of Stock') }}</div>
            </div>
        </div>
    </div>

    {{-- FILTERS & SEARCH --}}
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            {{-- Search --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1.5 uppercase tracking-wide">
                    <i class="fas fa-search mr-1"></i>{{ __('Search product') }}
                </label>
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="{{ __('Search products') }}"
                           class="w-full pl-3 pr-8 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @if($search || $categoryFilter !== 'all' || $stockFilter)
                        <button wire:click="clearSearch"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-red-500 transition-colors">
                            <i class="fas fa-times-circle text-sm"></i>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Category Filter --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1.5 uppercase tracking-wide">
                    <i class="fas fa-tags mr-1"></i>{{ __('Category') }}
                </label>
                <select wire:model.live.debounce.300ms="categoryFilter"
                        wire:key="category-filter-{{ $categoryFilter }}"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                               bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                               focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition cursor-pointer">
                    <option value="all">{{ __('All Categories') }}</option>
                    @foreach($categories as $key => $category)
                        <option value="{{ $key }}">{{ __($category) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Stock Filter --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1.5 uppercase tracking-wide">
                    <i class="fas fa-layer-group mr-1"></i>{{ __('Stock Level') }}
                </label>
                <select wire:model.live.debounce.300ms="stockFilter"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                               bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                               focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition cursor-pointer">
                    <option value="">{{ __('All Stock Levels') }}</option>
                    <option value="in_stock">{{ __('In Stock') }}</option>
                    <option value="low_stock">{{ __('Low Stock') }}</option>
                    <option value="out_of_stock">{{ __('Out of Stock') }}</option>
                    <option value="available">{{ __('Available') }}</option>
                    <option value="hidden">{{ __('Hidden') }}</option>
                </select>
            </div>

            {{-- Results count --}}
            <div class="flex items-end lg:col-span-1 lg:justify-end">
                <div class="text-xs text-zinc-500 dark:text-zinc-400 py-2">
                    @if($search || $categoryFilter !== 'all' || $stockFilter)
                        <i class="fas fa-filter mr-1 text-blue-500"></i>
                        {{ __('Filtered') }}: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $products->total() }}</span> {{ __('products results') }}
                    @else
                        <i class="fas fa-list mr-1"></i>
                        {{ __('Total') }}: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $products->total() }}</span> {{ __('products') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Loading overlay --}}
    @include('livewire.partials.loading-overlay', [
        'wireTarget' => 'categoryFilter,stockFilter,search,sortByField,openEditModal,openDeleteModal,save,archiveProduct,deleteProduct,makeAvailable',
        'title' => __('Updating...'),
        'message' => __('Please wait while we process your request'),
    ])

    @php
        $categoryNames = \App\Models\Product::getCategories();
    @endphp

    {{-- PRODUCT LIST --}}
    {{-- ── Mobile Cards (< lg) ── --}}
    @include('livewire.partials.products.orientation.mobile')

    {{-- ── Desktop Table (≥ lg) ── --}}
    @include('livewire.partials.products.orientation.desktop')

    {{-- Mobile pagination --}}
    <div class="lg:hidden mt-3">
        @if($products->hasPages())
            {{ $products->links() }}
        @endif
    </div>

    {{-- MODALS --}}
    {{-- Form Modal (create/edit) --}}
    @include('livewire.partials.products.form')
    {{-- Admin Password Verification Modal --}}
    @include('livewire.partials.products.admin-password-modal')

    {{-- HIDE / ARCHIVE CONFIRM MODAL --}}
    <div x-show="showArchiveModal"
        x-cloak
        wire:key="archive-confirm-modal"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="absolute inset-0 bg-black/50" @click="closeArchiveModal()"></div>

        <div x-show="showArchiveModal"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-sm bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-eye-slash text-green-600 dark:text-green-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Hide Product') }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    {{ __('Are you sure you want to hide this product? It will be marked as unavailable for sale.') }}
                </p>
                <div class="flex justify-center gap-2">
                    <button @click="closeArchiveModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button wire:click="archiveProduct" @click="closeArchiveModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-green-600 text-white hover:bg-green-700 active:scale-95 transition-all">
                        <i class="fas fa-eye-slash mr-1"></i>{{ __('Confirm Hide') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- DELETE CONFIRM MODAL --}}
    <div x-show="showDeleteModal"
        x-cloak
        wire:key="delete-confirm-modal"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="absolute inset-0 bg-black/50" @click="closeDeleteModal()"></div>

        <div x-show="showDeleteModal"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-sm bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-red-600 dark:text-red-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Delete Product') }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    {{ __('Are you sure you want to delete this product? This action cannot be undone.') }}
                </p>
                <div class="flex justify-center gap-2">
                    <button @click="closeDeleteModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button wire:click="deleteProduct" @click="closeDeleteModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-red-600 text-white hover:bg-red-700 active:scale-95 transition-all">
                        <i class="fas fa-trash mr-1"></i>{{ __('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- RESTOCK MODAL (Staff) --}}
    <div wire:ignore.self
        x-show="$wire.showRestockModal"
        x-cloak
        wire:key="restock-modal"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="absolute inset-0 bg-black/50" wire:click="closeRestockModal"></div>

        <div x-show="$wire.showRestockModal"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-sm bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                        <i class="fas fa-plus-circle text-emerald-600 dark:text-emerald-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Add Stock') }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.restockProductName"></p>
                    </div>
                </div>

                <div class="mb-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                        {{ __('Current stock:') }}
                        <span class="font-bold text-zinc-700 dark:text-zinc-300" x-text="$wire.restockCurrentStock"></span>
                    </p>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        {{ __('Quantity to Add') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number" wire:model="restockQty" min="1" max="9999"
                        placeholder="e.g. 10"
                        class="w-full px-3 py-2.5 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                               bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                               focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 transition"
                        x-on:keydown.enter.prevent="$wire.confirmRestock()">
                    @error('restockQty')
                        <p class="mt-1 text-xs text-red-500"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="closeRestockModal"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button wire:click="confirmRestock"
                        wire:loading.attr="disabled"
                        wire:target="confirmRestock"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition-all disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmRestock">
                            <i class="fas fa-plus mr-1"></i>{{ __('Confirm Add') }}
                        </span>
                        <span wire:loading wire:target="confirmRestock">
                            <i class="fas fa-spinner fa-spin mr-1"></i>{{ __('Processing...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- VARIANTS MODAL (規格管理) --}}
    <div wire:ignore.self
        x-show="$wire.showVariantsModal"
        x-cloak
        wire:key="variants-modal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">

        <div class="absolute inset-0 bg-black/50" wire:click="closeVariantsModal"></div>

        <div x-show="$wire.showVariantsModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90dvh] overflow-y-auto z-10">

            {{-- Header --}}
            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <i class="fas fa-layer-group text-purple-500"></i>
                        商品規格管理
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5" x-text="$wire.variantsProductName"></p>
                </div>
                <button wire:click="closeVariantsModal"
                    class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-5 space-y-5">

                {{-- 現有規格列表 --}}
                <div>
                    <h4 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-3">現有規格</h4>
                    @if(count($variants) === 0)
                        <div class="text-center py-8 text-zinc-400 dark:text-zinc-500">
                            <i class="fas fa-layer-group text-3xl mb-2 opacity-40"></i>
                            <p class="text-sm">尚未新增任何規格</p>
                            <p class="text-xs mt-1 opacity-70">在下方新增第一個規格</p>
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($variants as $v)
                                @if($editingVariantId === $v['id'])
                                    {{-- 編輯模式 --}}
                                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-xl p-3 space-y-3">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">規格名稱</label>
                                                <input type="text" wire:model="editVariantName"
                                                    class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                                                @error('editVariantName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">規格類型</label>
                                                <select wire:model="editVariantType"
                                                    class="cursor-pointer w-full px-2.5 py-1.5 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                                                    <option value="general">一般規格</option>
                                                    <option value="color">顏色</option>
                                                    <option value="size">尺寸</option>
                                                    <option value="flavor">口味</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">價格（空白=繼承主價）</label>
                                                <input type="number" step="0.01" wire:model="editVariantPrice" placeholder="繼承主價"
                                                    class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                                                @error('editVariantPrice') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">庫存數量</label>
                                                <input type="number" wire:model="editVariantStocks"
                                                    class="w-full px-2.5 py-1.5 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                                                @error('editVariantStocks') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                            </div>
                                        </div>
                                        <div class="flex gap-2 justify-end">
                                            <button wire:click="cancelEditVariant"
                                                class="cursor-pointer px-3 py-1.5 text-xs font-medium rounded-lg border border-zinc-200 dark:border-zinc-600 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition">
                                                <i class="fas fa-times mr-1"></i>取消
                                            </button>
                                            <button wire:click="saveVariant"
                                                class="cursor-pointer px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition">
                                                <i class="fas fa-check mr-1"></i>儲存
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    {{-- 顯示模式 --}}
                                    <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl border
                                        {{ $v['is_active'] ? 'border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/40' : 'border-zinc-100 dark:border-zinc-700/50 bg-zinc-50/50 dark:bg-zinc-800/50 opacity-60' }}">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $v['name'] }}</span>
                                                <span class="text-xs px-1.5 py-0.5 rounded-full
                                                    {{ $v['type'] === 'color' ? 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300' :
                                                       ($v['type'] === 'size' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' :
                                                       ($v['type'] === 'flavor' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' :
                                                       'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300')) }}">
                                                    {{ match($v['type']) { 'color' => '顏色', 'size' => '尺寸', 'flavor' => '口味', default => '規格' } }}
                                                </span>
                                                @if(! $v['is_active'])
                                                    <span class="text-xs text-zinc-400">（已停用）</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span><i class="fas fa-peso-sign mr-0.5"></i>{{ $v['price'] !== null ? number_format($v['price'], 2) : '繼承主價' }}</span>
                                                <span><i class="fas fa-cubes mr-0.5"></i>庫存: {{ $v['stocks'] }}</span>
                                                <span><i class="fas fa-chart-bar mr-0.5"></i>已售: {{ $v['sold'] }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button wire:click="startEditVariant({{ $v['id'] }})"
                                                class="cursor-pointer p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 transition"
                                                title="編輯">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button wire:click="toggleVariantActive({{ $v['id'] }})"
                                                class="cursor-pointer p-1.5 rounded-lg transition
                                                    {{ $v['is_active'] ? 'text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20' : 'text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                                                title="{{ $v['is_active'] ? '停用規格' : '啟用規格' }}">
                                                <i class="fas {{ $v['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' }} text-sm"></i>
                                            </button>
                                            <button wire:click="deleteVariant({{ $v['id'] }})"
                                                class="cursor-pointer p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition"
                                                title="刪除規格"
                                                onclick="return confirm('確定要刪除規格「{{ $v['name'] }}」？')">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 新增規格表單 --}}
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <h4 class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-3">新增規格</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">規格名稱 <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="newVariantName" placeholder="例：紅色、L號、原味"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500 transition">
                            @error('newVariantName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">規格類型</label>
                            <select wire:model="newVariantType"
                                class="cursor-pointer w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500 transition">
                                <option value="general">一般規格</option>
                                <option value="color">顏色</option>
                                <option value="size">尺寸</option>
                                <option value="flavor">口味</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">規格價格 <span class="text-zinc-400 font-normal">(空白=繼承主價)</span></label>
                            <input type="number" step="0.01" wire:model="newVariantPrice" placeholder="繼承主價"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500 transition">
                            @error('newVariantPrice') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">初始庫存 <span class="text-red-500">*</span></label>
                            <input type="number" wire:model="newVariantStocks" min="0"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500 transition">
                            @error('newVariantStocks') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end mt-3">
                        <button wire:click="addVariant"
                            wire:loading.attr="disabled"
                            wire:target="addVariant"
                            class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-purple-600 text-white hover:bg-purple-700 active:scale-95 transition-all shadow-md shadow-purple-500/20">
                            <span wire:loading.remove wire:target="addVariant">
                                <i class="fas fa-plus mr-1"></i>新增規格
                            </span>
                            <span wire:loading wire:target="addVariant">
                                <i class="fas fa-spinner fa-spin mr-1"></i>存入中...
                            </span>
                        </button>
                    </div>
                </div>

                {{-- 說明 --}}
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-xl p-3">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>說明：</strong>新增規格後，商品主庫存將自動同步為所有規格庫存的加總。在訂單中選取此商品時，將顯示規格選擇選單。
                    </p>
                </div>

            </div>
        </div>
    </div>

<style>
    .prod-card-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.625rem;
        border-radius: 0.625rem;
        font-size: 0.75rem;
        font-weight: 500;
        transition: background-color 0.15s;
        cursor: pointer;
        white-space: nowrap;
    }
    .tbl-action-btn {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.125rem;
        padding: 0.375rem 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        transition: background-color 0.15s;
        cursor: pointer;
        white-space: nowrap;
        min-width: 3rem;
        text-align: center;
    }
</style>
</div>
