{{-- 管理員密碼驗證 Modal --}}
{{-- 當員工嘗試修改庫存數量時，需要輸入管理員密碼才能繼續 --}}
<div
    x-show="$wire.showAdminPasswordModal"
    x-cloak
    wire:key="admin-password-modal"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-[60]"
    @click.self="$wire.closeAdminPasswordModal()">

    <div
        x-show="$wire.showAdminPasswordModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative bg-white dark:bg-zinc-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-amber-50 dark:bg-amber-900/20">
            <h3 class="text-base font-semibold text-amber-900 dark:text-amber-100 flex items-center gap-2">
                <i class="fas fa-lock text-amber-600 dark:text-amber-400"></i>
                {{ __('Admin Authorization Required') }}
            </h3>
            <button wire:click="closeAdminPasswordModal"
                class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="p-5">
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                <i class="fas fa-info-circle mr-1 text-amber-500"></i>
                {{ __('Modifying stock quantity requires admin authorization. Please enter the admin password to continue.') }}
            </p>

            <div>
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    <i class="fas fa-key mr-1"></i>{{ __('Admin Password') }}
                </label>
                <input
                    type="password"
                    wire:model="adminPasswordInput"
                    wire:keydown.enter="confirmAdminPassword"
                    placeholder="{{ __('Enter admin password') }}"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition"
                    autofocus>
                @error('adminPasswordInput')
                    <p class="text-red-500 text-xs mt-1.5">
                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex justify-end gap-2 px-5 pb-5">
            <button type="button" wire:click="closeAdminPasswordModal"
                class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
            </button>
            <button type="button" wire:click="confirmAdminPassword"
                class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition-all shadow-md shadow-amber-500/20">
                <i class="fas fa-unlock mr-1"></i>{{ __('Authorize') }}
            </button>
        </div>
    </div>
</div>
