{{--
    Inline Product Creation Panel
    Rendered when $showProductForm is truthy.
    Used in: Create Order, Record Sales
    Not used in: Edit Order (edit doesn't support creating products inline)

    Props:
        $subtitle – optional string shown below the title
--}}

@if($showProductForm)
<div class="mb-5 rounded-2xl border border-blue-200 dark:border-blue-800/50
            bg-blue-50/60 dark:bg-blue-950/20 p-4 space-y-4">

    {{-- Panel header --}}
    <div class="flex items-start justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <i class="fas fa-box-open text-blue-500"></i>{{ __('Create Product') }}
            </h4>
        </div>
        <button type="button" wire:click="closeProductForm"
            class="shrink-0 text-xs font-semibold text-red-500 hover:text-red-600 transition">
            <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
        </button>
    </div>

    {{-- Fields --}}
    @php
        $inputClass = "w-full px-3 py-2.5 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                       bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100
                       focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition";
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

        <div>
            <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                {{ __('Product Name') }} <span class="text-red-500 normal-case font-normal">*</span>
            </label>
            <input type="text" wire:model="productName" class="{{ $inputClass }}">
            @error('productName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                {{ __('Category') }} <span class="text-red-500 normal-case font-normal">*</span>
            </label>
            <select wire:model="productCategory" class="{{ $inputClass }}">
                <option value="">{{ __('-- Select Category --') }}</option>
                @foreach(\App\Models\Product::getCategories() as $key => $category)
                    <option value="{{ $key }}">{{ $category }}</option>
                @endforeach
            </select>
            @error('productCategory') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                {{ __('Unit Price') }} <span class="text-red-500 normal-case font-normal">*</span>
            </label>
            <input type="number" step="0.01" min="0" wire:model="productPrice" class="{{ $inputClass }}">
            @error('productPrice') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                {{ __('Stock Quantity') }} <span class="text-red-500 normal-case font-normal">*</span>
            </label>
            <input type="number" min="0" wire:model="productStocks" class="{{ $inputClass }}">
            @error('productStocks') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                {{ __('Description') }} <span class="text-zinc-400 normal-case font-normal">{{ __('(optional)') }}</span>
            </label>
            <textarea wire:model="productDescription" rows="2" class="{{ $inputClass }}"></textarea>
        </div>
    </div>

    {{-- Submit --}}
    <div class="flex justify-end">
        <button type="button" wire:click="createProduct"
            wire:loading.attr="disabled"
            wire:target="createProduct"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white
                   text-sm font-semibold hover:bg-blue-700 active:scale-95
                   disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <span wire:loading.remove wire:target="createProduct">
                <i class="fas fa-save mr-1"></i>{{ __('Create Product') }}
            </span>
            <span wire:loading wire:target="createProduct" class="flex items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i>{{ __('Creating') }}
            </span>
        </button>
    </div>
</div>
@endif
