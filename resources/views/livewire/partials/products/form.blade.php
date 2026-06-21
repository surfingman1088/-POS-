<div x-show="showFormModal"
    x-cloak
    wire:key="product-form-modal"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-black/60 flex items-end sm:items-center justify-center p-0 sm:p-4 z-50"
    @click.self="closeFormModal()">

    <div x-show="showFormModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
        class="relative bg-white dark:bg-zinc-800 w-full sm:rounded-2xl sm:max-w-2xl max-h-[92dvh] overflow-y-auto shadow-2xl">

        {{-- Spinner while edit data loads --}}
        <div x-show="editLoading" x-cloak
             class="absolute inset-0 bg-white/70 dark:bg-zinc-800/70 z-20 flex items-center justify-center">
            <i class="fas fa-circle-notch fa-spin text-2xl text-blue-500"></i>
        </div>

        <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <i class="fas" :class="formMode === 'create' ? 'fa-plus-circle text-blue-500' : 'fa-edit text-blue-500'"></i>
                <span x-text="formMode === 'create' ? @js(__('Add New Product')) : @js(__('Edit Product'))"></span>
            </h3>
            <button @click="closeFormModal()"
                class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Product Name --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-tag mr-1"></i>{{ __('Product Name') }}
                    </label>
                    <input type="text" wire:model="name"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('name') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-folder mr-1"></i>{{ __('Category') }}
                    </label>
                    <select wire:model="category"
                        class="cursor-pointer w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                        <option value="">{{ __('Select Category') }}</option>
                        @foreach($categories as $key => $categoryName)
                            <option value="{{ $key }}">{{ __($categoryName) }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Price --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-peso-sign mr-1"></i>{{ __('Price') }}
                        <span class="normal-case font-normal ml-1">({{ __('per unit or kilo') }})</span>
                    </label>
                    <input type="number" step="0.01" wire:model="price"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('price') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Cost (Admin only) --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-dollar-sign mr-1"></i>{{ __('Cost Price') }}
                        <span class="normal-case font-normal ml-1 text-amber-600 dark:text-amber-400">({{ __('admin only') }})</span>
                    </label>
                    <input type="number" step="0.01" wire:model="cost"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-amber-200 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-900/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition">
                    @error('cost') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Stocks --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-cubes mr-1"></i>{{ __('Stocks') }}
                        <span class="normal-case font-normal ml-1">({{ __('per unit or kilo') }})</span>
                    </label>
                    <input type="number" wire:model="stocks"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('stocks') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Image --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-image mr-1"></i>{{ __('Product Image') }}
                        <span class="normal-case font-normal ml-1">({{ __('optional') }})</span>
                    </label>

                    <div class="flex items-center gap-4">
                        {{-- Square preview --}}
                        <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden border-2 border-dashed border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/40 shrink-0">
                            @if($image)
                                <img src="{{ $image->temporaryUrl() }}" class="absolute inset-0 w-full h-full object-cover">
                            @elseif($existingImageUrl)
                                <img src="{{ $existingImageUrl }}" class="absolute inset-0 w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-zinc-300 dark:text-zinc-500">
                                    <i class="fas fa-image text-2xl"></i>
                                </div>
                            @endif

                            @if($image || $existingImageUrl)
                                <button type="button" wire:click="removeImage"
                                    class="cursor-pointer absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 hover:bg-red-600 text-white flex items-center justify-center transition-colors"
                                    title="{{ __('Remove image') }}">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            @endif
                        </div>

                        {{-- wire:key forces a fresh, empty <input type="file"> after removal --}}
                        <div class="flex-1 min-w-0" wire:key="image-upload-{{ $imageVersion }}">
                            <input type="file" accept="image/*" wire:model="image"
                                class="w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer">

                            <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1.5">
                                <i class="fas fa-crop mr-1"></i>{{ __('Automatically cropped to a square.') }}
                            </p>

                            <div wire:loading wire:target="image" class="text-xs text-zinc-400 mt-1">
                                <i class="fas fa-circle-notch fa-spin mr-1"></i>{{ __('Uploading...') }}
                            </div>
                            @error('image') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Color Picker --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-palette mr-1"></i>{{ __('Label Color') }}
                        <span class="normal-case font-normal ml-1">({{ __('optional, unique') }})</span>
                    </label>

                    @if($color)
                        <div class="flex items-center gap-2" x-data="{ colorValue: $wire.entangle('color') }">
                            <input type="color"
                                x-model="colorValue"
                                @change="$wire.set('color', colorValue)"
                                class="w-12 h-10 p-0 border-0 bg-transparent cursor-pointer rounded-lg shrink-0">

                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono" x-text="colorValue || '#000000'"></span>

                            <button type="button" wire:click="regenerateColor"
                                class="cursor-pointer ml-auto text-xs text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                                <i class="fas fa-dice"></i>{{ __('New') }}
                            </button>
                            <button type="button" wire:click="removeColor"
                                class="cursor-pointer text-xs text-red-600 dark:text-red-400 hover:underline inline-flex items-center gap-1">
                                <i class="fas fa-times"></i>{{ __('Remove') }}
                            </button>
                        </div>
                    @else
                        <button type="button" wire:click="regenerateColor"
                            class="cursor-pointer w-full flex items-center justify-center gap-2 px-3 py-2 text-sm rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 text-zinc-400 dark:text-zinc-500 hover:border-blue-400 hover:text-blue-500 transition">
                            <i class="fas fa-plus"></i>{{ __('Assign a color') }}
                        </button>
                    @endif
                    @error('color') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Available for Sale --}}
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" wire:model="is_in_stock"
                            class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">
                            <i class="fas fa-check-circle mr-1 text-green-500"></i>{{ __('Available for Sale') }}
                        </span>
                    </label>
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    <i class="fas fa-align-left mr-1"></i>{{ __('Description') }}
                    <span class="normal-case font-normal ml-1">({{ __('optional') }})</span>
                </label>
                <textarea wire:model="description" rows="3"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition resize-none"></textarea>
                @error('description') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="closeFormModal()"
                    class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                </button>
                <button type="submit"
                    class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                    <i class="fas fa-save mr-1"></i>
                    <span x-text="formMode === 'create' ? @js(__('Create Product')) : @js(__('Save Changes'))"></span>
                </button>
            </div>
        </form>
    </div>
</div>
