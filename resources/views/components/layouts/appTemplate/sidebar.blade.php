<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        @include('partials.head')
        @stack('styles')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-gray-900">

        @php
            $dashboardCurrent = request()->routeIs('dashboard');
            $ordersCurrent = request()->routeIs('orders');
            $ordersHistoryCurrent = request()->routeIs('orders.history');
            $productsCurrent = request()->routeIs('products');
            $productsCategoriesCurrent = request()->routeIs('products.categories');
            $customersCurrent = request()->routeIs('customers', 'customers.*');
            $employeesCurrent = request()->routeIs('employees', 'employees.*');
            $logsCurrent = request()->routeIs('logs', 'logs.*');
            $warehouseCurrent = request()->routeIs('warehouse.*');
            $canAccessWarehouse = Auth::check() && Auth::user()->canAccessWarehouse();
            $canManageWarehouse = Auth::check() && Auth::user()->canManageWarehouse();
            $isWarehouseOrBranchOnly = Auth::check() && in_array(Auth::user()->role, ['warehouse', 'branch']);
        @endphp

        {{-- Nav Bar (sidebar, header) --}}
        <flux:sidebar sticky stashable class="lg:flex border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" inset="left" />

            <a href="{{ $isWarehouseOrBranchOnly ? route('warehouse.dashboard') : route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            {{-- main nav group（倉庫/分店人員不顯示 POS 相關選單）--}}
            @if(!$isWarehouseOrBranchOnly)
            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Shop')" class="grid">
                    {{-- dashboard --}}
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="$dashboardCurrent" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                {{-- orders nav group --}}
                <flux:navlist.group :heading="__('Orders')" class="grid mt-2.5">
                    {{-- orders --}}
                    <flux:navlist.item icon="shopping-cart" :href="route('orders')" :current="$ordersCurrent" wire:navigate>{{ __('Orders') }}</flux:navlist.item>
                    {{-- orders records --}}
                    <flux:navlist.item icon="clock" :href="route('orders.history')" :current="$ordersHistoryCurrent" wire:navigate>{{ __('Orders Records') }}</flux:navlist.item>
                </flux:navlist.group>

                {{-- products nav group --}}
                <flux:navlist.group :heading="__('Products')" class="grid mt-2.5">
                    {{-- products --}}
                    <flux:navlist.item icon="shopping-bag" :href="route('products')" :current="$productsCurrent" wire:navigate>{{ __('Products') }}</flux:navlist.item>
                    {{-- categories --}}
                    <flux:navlist.item icon="tag" :href="route('products.categories')" :current="$productsCategoriesCurrent" wire:navigate>{{ __('Categories') }}</flux:navlist.item>
                    {{-- Inventory Audit --}}
                    <flux:navlist.item icon="chart-bar" :href="route('inventory.audit')" :current="request()->routeIs('inventory.audit')" wire:navigate>{{ __('Inventory Audit') }}</flux:navlist.item>
                </flux:navlist.group>

                {{-- management nav group --}}
                <flux:navlist.group :heading="__('Management')" class="grid mt-2.5">
                    {{-- customers --}}
                    <flux:navlist.item icon="identification" :href="route('customers')" :current="$customersCurrent" wire:navigate>{{ __("Customers") }}</flux:navlist.item>

                    {{-- 以下項目僅管理員可見 --}}
                    @if(Auth::check() && Auth::user()->isAdmin())
                    {{-- employees --}}
                    <flux:navlist.item icon="users" :href="route('employees')" :current="$employeesCurrent" wire:navigate>{{ __("Employees") }}</flux:navlist.item>
                    {{-- Discount Presets --}}
                    <flux:navlist.item icon="receipt-percent" :href="route('presets.discounts')" :current="request()->routeIs('presets.discounts')" wire:navigate>{{ __('Discount Presets') }}</flux:navlist.item>
                    {{-- Accounts & Sessions --}}
                    <flux:navlist.item icon="user-circle" :href="route('accounts.sessions')" :current="request()->routeIs('accounts.sessions')" wire:navigate>{{ __('Accounts & Sessions') }}</flux:navlist.item>
                    {{-- System Logs --}}
                    <flux:navlist.item icon="server" :href="route('logs')" :current="$logsCurrent" wire:navigate>{{ __('System Logs') }}</flux:navlist.item>
                    <flux:navlist.item icon="shield-exclamation" :href="route('anomaly.monitor')" :current="request()->routeIs('anomaly.monitor')" wire:navigate>異常監控</flux:navlist.item>
                    @endif
                </flux:navlist.group>

            </flux:navlist>
            @endif

            {{-- 倉儲管理導覽選單（admin / warehouse / branch 角色可見）--}}
            @if($canAccessWarehouse)
            <flux:navlist variant="outline" class="{{ $isWarehouseOrBranchOnly ? '' : 'mt-2.5' }}">
                <flux:navlist.group :heading="__('倉儲管理')" class="grid">
                    {{-- 倉儲總覽 --}}
                    <flux:navlist.item icon="archive-box" :href="route('warehouse.dashboard')" :current="request()->routeIs('warehouse.dashboard')" wire:navigate>{{ __('倉儲總覽') }}</flux:navlist.item>

                    {{-- 入庫管理（倉庫管理員 + 總管理員）--}}
                    @if($canManageWarehouse)
                    <flux:navlist.item icon="arrow-down-tray" :href="route('warehouse.receipt')" :current="request()->routeIs('warehouse.receipt')" wire:navigate>{{ __('入庫管理') }}</flux:navlist.item>
                    {{-- 出庫管理 --}}
                    <flux:navlist.item icon="arrow-up-tray" :href="route('warehouse.dispatch')" :current="request()->routeIs('warehouse.dispatch')" wire:navigate>{{ __('出庫管理') }}</flux:navlist.item>
                    {{-- 庫存盤點 --}}
                    <flux:navlist.item icon="clipboard-document-check" :href="route('warehouse.stocktake')" :current="request()->routeIs('warehouse.stocktake')" wire:navigate>{{ __('庫存盤點') }}</flux:navlist.item>
                    @endif

                    {{-- 分店庫存查詢（所有倉儲角色可見）--}}
                    <flux:navlist.item icon="building-storefront" :href="route('warehouse.branch-stock')" :current="request()->routeIs('warehouse.branch-stock')" wire:navigate>{{ __('分店庫存') }}</flux:navlist.item>

                    {{-- 異動記錄（倉庫管理員 + 總管理員）--}}
                    @if($canManageWarehouse)
                    <flux:navlist.item icon="document-text" :href="route('warehouse.movements')" :current="request()->routeIs('warehouse.movements')" wire:navigate>{{ __('異動記錄') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>
            </flux:navlist>
            @endif

            {{-- add space  --}}
            <flux:spacer />

            {{-- Desktop User Menu --}}
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="Auth::user()->name"
                    :initials="Auth::user()->initials()"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ Auth::user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ Auth::user()->name }}</span>
                                    <span class="truncate text-xs text-orange-500">{{ Auth::user()->role_label }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate class="cursor-pointer">{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="cursor-pointer w-full hover:bg-red-500">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>

        </flux:sidebar>

        {{-- Mobile User Menu --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="Auth::user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ Auth::user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ Auth::user()->name }}</span>
                                    <span class="truncate text-xs text-orange-500">{{ Auth::user()->role_label }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        {{-- scripts --}}
        @include('components.scripts.toastrjs')

        @stack('scripts')

        @fluxScripts
    </body>
</html>
