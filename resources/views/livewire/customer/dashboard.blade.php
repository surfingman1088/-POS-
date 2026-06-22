@section('title', __('Customer Management'))

<div class="w-full max-w-full overflow-x-hidden px-2 sm:px-4 pb-8"
     x-data="{
         showCreateModal: false,
         showEditModal: false,
         showDeleteModal: false,

         openCreateModal()  { this.showCreateModal = true;  $wire.resetForm(); },
         closeCreateModal() { this.showCreateModal = false; $wire.resetForm(); },
         openEditModal(id)  { this.showEditModal = true;    $wire.loadCustomerForEdit(id); },
         closeEditModal()   { this.showEditModal = false;   $wire.resetForm(); },
         openDeleteModal(id)  { this.showDeleteModal = true;  $wire.setSelectedCustomer(id); },
         closeDeleteModal()   { this.showDeleteModal = false; $wire.setSelectedCustomer(null); },
     }"
     @close-create-modal.window="closeCreateModal()"
     @close-edit-modal.window="closeEditModal()"
     @close-delete-modal.window="closeDeleteModal()">

    {{-- ═══════════════════════════════════════════════
         HEADER
    ════════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-3 py-2 mb-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <i class="fas fa-users text-blue-500"></i>
                {{ __('Customer Management') }}
            </h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                {{ __('Manage your customers and their information') }}
            </p>
        </div>
        <button @click="openCreateModal()"
            class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold
                   hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20 shrink-0">
            <i class="fas fa-user-plus"></i>
            <span>{{ __('Add Customer') }}</span>
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════
         QUICK STATS  (single row, 4-col)
    ════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">

        {{-- Total Customers --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">{{ $customers->total() }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ __('Total Customers') }}</div>
            </div>
        </div>

        {{-- Avg Orders --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-chart-line text-green-600 dark:text-green-400"></i>
            </div>
            <div class="min-w-0">
                @php
                    $totalCustomers = $allCustomers->count();
                    $totalOrders    = $allCustomers->sum(fn($c) => $c->orders()->count());
                    $avgOrders      = $totalCustomers > 0 ? round($totalOrders / $totalCustomers, 1) : 0;
                @endphp
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">{{ $avgOrders }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ __('Average Orders per Customer') }}</div>
            </div>
        </div>

        {{-- Repeated Customers --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-repeat text-amber-600 dark:text-amber-400"></i>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">{{ $repeatedCustomersThisMonth }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ __('This Month\'s Repeated Customers') }}</div>
            </div>
        </div>

        {{-- New This Month --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center shrink-0">
                <i class="fas fa-user-plus text-purple-600 dark:text-purple-400"></i>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">
                    {{ $allCustomers->where('created_at', '>=', now()->startOfMonth())->count() }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">{{ __('New Customers this Month') }}</div>
            </div>
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            <div class="flex-1 w-full">
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    <i class="fas fa-search mr-1"></i>{{ __('Search Customer') }}
                </label>
                <div class="relative">
                    <input type="text"
                           wire:model.live="search"
                           placeholder="{{ __('Search by name, address, unit, or contact number') }}"
                           class="w-full pl-3 pr-9 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @if($search)
                        <button wire:click="$set('search', '')"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-red-500 transition-colors">
                            <i class="fas fa-times-circle text-sm"></i>
                        </button>
                    @else
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-300 dark:text-zinc-600 pointer-events-none">
                            <i class="fas fa-search text-sm"></i>
                        </span>
                    @endif
                </div>
            </div>

            @if($search)
                <div class="text-xs text-zinc-500 dark:text-zinc-400 sm:mt-5 shrink-0">
                    <i class="fas fa-filter mr-1 text-blue-500"></i>
                    {{ __('Showing results for') }}: <span class="font-semibold text-zinc-700 dark:text-zinc-300">"{{ $search }}"</span>
                    · <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $customers->total() }}</span> {{ __('found') }}
                </div>
            @endif
        </div>
    </div>

    {{-- CUSTOMER LIST --}}

    {{-- ── Mobile Cards (< lg) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
        @forelse($customers as $customer)
            @php $orderCount = $customer->orders_count ?? 0; @endphp

            <div wire:key="mobile-customer-{{ $customer->id }}"
                 class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden">

                {{-- Accent strip: green if has orders, zinc if none --}}
                <div class="h-1 w-full {{ $orderCount > 0 ? 'bg-blue-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>

                <div class="p-4 space-y-3">
                    {{-- Avatar + Name + ID --}}
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center shrink-0 shadow-sm">
                            <span class="text-white text-sm font-bold">
                                {{ strtoupper(substr($customer->name, 0, 1)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $customer->name }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                                <i class="fas fa-hashtag mr-0.5"></i>{{ $customer->id }}
                            </p>
                        </div>
                        {{-- Orders badge --}}
                        <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold shrink-0
                                     {{ $orderCount > 0
                                         ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                         : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}">
                            <i class="fas fa-shopping-bag text-[10px]"></i>{{ $orderCount }}
                        </span>
                    </div>

                    {{-- Info rows --}}
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-start gap-2 text-zinc-600 dark:text-zinc-400">
                            <i class="fas fa-map-marker-alt mt-0.5 w-3.5 shrink-0 text-zinc-400"></i>
                            @if($customer->unit || $customer->address)
                                <span class="truncate">
                                    {{ implode(', ', array_filter([$customer->unit, $customer->address])) }}
                                </span>
                            @else
                                <span class="italic text-zinc-400 dark:text-zinc-500">No address provided</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                            <i class="fas fa-mobile-alt w-3.5 shrink-0 text-zinc-400"></i>
                            @if($customer->contact_number)
                                <span>{{ $customer->contact_number }}</span>
                            @else
                                <span class="italic text-zinc-400 dark:text-zinc-500">No contact number</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-zinc-400 dark:text-zinc-500">
                            <i class="fas fa-calendar w-3.5 shrink-0"></i>
                            <span>{{ $customer->created_at->translatedFormat('M d, Y') }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-700">
                        @if(auth()->check() && auth()->user()->isAdmin())
                            <button @click="openEditModal({{ $customer->id }})"
                                class="cust-card-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
                                <i class="fas fa-edit"></i>{{ __('Edit') }}
                            </button>
                            <button @click="openDeleteModal({{ $customer->id }})"
                                class="cust-card-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 ml-auto">
                                <i class="fas fa-trash"></i>{{ __('Delete') }}
                            </button>
                        @else
                            <span class="text-xs text-zinc-400 italic px-1">{{ __('View Only') }}</span>
                        @endif
                    </div>
                </div>
            </div>

        @empty
            <div class="sm:col-span-2 flex flex-col items-center justify-center py-20 text-zinc-400 dark:text-zinc-500">
                <i class="fas fa-users text-5xl mb-4 opacity-40"></i>
                @if($search)
                    <p class="text-sm">{{ __('No customers found.') }}</p>
                    <button wire:click="$set('search', '')"
                        class="cursor-pointer mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 transition-colors">
                        <i class="fas fa-times"></i>{{ __('Clear search') }}
                    </button>
                @else
                    <p class="text-sm">{{ __('No customers found.') }}</p>
                    <p class="text-xs mt-1 opacity-70">{{ __('Add your first customer to get started.') }}</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- ── Desktop Table (≥ lg) ── --}}
    <div class="hidden lg:block bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                    <tr>
                        <th wire:click="sortByField('id')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none w-16">
                            <div class="flex items-center justify-center gap-1">
                                <i class="fas fa-hashtag"></i>
                                @if($sortBy === 'id')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortByField('name')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                <i class="fas fa-user"></i>
                                {{ __('Customer Name') }}
                                @if($sortBy === 'name')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <i class="fas fa-map-marker-alt mr-1"></i>{{ __('Unit & Address') }}
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <i class="fas fa-phone mr-1"></i>{{ __('Contact Number') }}
                        </th>
                        <th wire:click="sortByField('orders_count')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                <i class="fas fa-shopping-bag"></i>
                                {{ __('Orders Count') }}
                                @if($sortBy === 'orders_count')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortByField('created_at')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                <i class="fas fa-calendar"></i>
                                {{ __('Created At') }}
                                @if($sortBy === 'created_at')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse($customers as $customer)
                        @php $orderCount = $customer->orders_count ?? 0; @endphp
                        <tr wire:key="desktop-customer-{{ $customer->id }}"
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40 transition-colors">

                            {{-- ID --}}
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-mono text-zinc-500 dark:text-zinc-400">#{{ $customer->id }}</span>
                            </td>

                            {{-- Name + Avatar --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center shrink-0 shadow-sm">
                                        <span class="text-white text-xs font-bold">
                                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $customer->name }}</span>
                                </div>
                            </td>

                            {{-- Unit & Address --}}
                            <td class="px-4 py-3 max-w-[200px]">
                                <div class="text-sm text-zinc-700 dark:text-zinc-300 truncate">
                                    @if($customer->unit || $customer->address)
                                        <i class="fas fa-home mr-1 text-zinc-400"></i>
                                        {{ implode(', ', array_filter([$customer->unit, $customer->address])) }}
                                    @else
                                        <span class="text-xs italic text-zinc-400 dark:text-zinc-500">No address provided</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Contact --}}
                            <td class="px-4 py-3 text-center">
                                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                    @if($customer->contact_number)
                                        <i class="fas fa-mobile-alt mr-1 text-zinc-400"></i>{{ $customer->contact_number }}
                                    @else
                                        <span class="text-xs italic text-zinc-400 dark:text-zinc-500">No contact number</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Orders --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                             {{ $orderCount > 0
                                                 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                 : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}">
                                    <i class="fas fa-shopping-bag text-[10px]"></i>{{ $orderCount }}
                                </span>
                            </td>

                            {{-- Created At --}}
                            <td class="px-4 py-3 text-center">
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <span class="block">{{ $customer->created_at->translatedFormat('M d, Y') }}</span>
                                    <span class="block text-zinc-400 dark:text-zinc-500">{{ $customer->created_at->translatedFormat('h:i:s A') }}</span>
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @if(auth()->check() && auth()->user()->isAdmin())
                                        <button @click="openEditModal({{ $customer->id }})"
                                            class="tbl-action-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                                            title="{{ __('Edit Customer') }}">
                                            <i class="fas fa-edit text-sm"></i>
                                            <span class="text-xs">{{ __('Edit') }}</span>
                                        </button>
                                        <button @click="openDeleteModal({{ $customer->id }})"
                                            class="tbl-action-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                            title="{{ __('Delete Customer') }}">
                                            <i class="fas fa-trash text-sm"></i>
                                            <span class="text-xs">{{ __('Delete') }}</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-zinc-400 italic px-2">{{ __('View Only') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center text-zinc-400 dark:text-zinc-500">
                                    <i class="fas fa-users text-5xl mb-4 opacity-40"></i>
                                    @if($search)
                                        <p class="text-sm">{{ __('No customers found.') }}</p>
                                        <button wire:click="$set('search', '')"
                                            class="cursor-pointer mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-times"></i>{{ __('Clear search') }}
                                        </button>
                                    @else
                                        <p class="text-sm">{{ __('No customers found.') }}</p>
                                        <p class="text-xs mt-1 opacity-70">{{ __('Add your first customer to get started.') }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-700">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    {{-- Mobile pagination --}}
    <div class="lg:hidden mt-3">
        @if($customers->hasPages())
            {{ $customers->links() }}
        @endif
    </div>


    {{-- CREATE CUSTOMER MODAL --}}
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 z-50">
        <div class="bg-white dark:bg-zinc-800 w-full sm:rounded-2xl sm:max-w-lg max-h-[92dvh] overflow-y-auto shadow-2xl">

            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-user-plus text-blue-500"></i>{{ __('Create New Customer') }}
                </h3>
                <button @click="closeCreateModal()"
                    class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form wire:submit.prevent="createCustomer" class="p-5 space-y-4">
                @csrf

                {{-- Name --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-user mr-1"></i>
                        {{ __('Customer Name') }}
                        <span class="text-red-500 normal-case font-normal">*</span>
                    </label>

                    <input type="text" wire:model="name"
                           placeholder="{{ __('Enter customer name') }}"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('name') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Unit & Address --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        {{ __('Unit & Address') }}
                        <span class="text-red-500 normal-case font-normal">*</span>
                    </label>

                    <div class="flex gap-2">
                        <input type="text" wire:model="unit"
                               placeholder="Unit 123"
                               class="w-28 px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                      bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                      focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                        <input type="text" wire:model="address"
                               placeholder="123 Sesame Street"
                               class="flex-1 px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                      bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                      focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    </div>
                    @error('unit')    <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                    @error('address') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Contact Number --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-phone mr-1"></i>
                        {{ __('Contact Number') }}
                        <span class="text-gray-500 normal-case font-normal">*</span>
                    </label>
                    <input maxlength="11" type="tel" inputmode="numeric" pattern="[0-9]*"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           wire:model="contact_number"
                           placeholder="{{ __('Enter contact number (e.g., 09123456789)') }}"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('contact_number') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeCreateModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button type="submit"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                        <i class="fas fa-user-plus mr-1"></i>{{ __('Create Customer') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         EDIT CUSTOMER MODAL
    ════════════════════════════════════════════════ --}}
    <div x-show="showEditModal" x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 z-50">
        <div class="bg-white dark:bg-zinc-800 w-full sm:rounded-2xl sm:max-w-lg max-h-[92dvh] overflow-y-auto shadow-2xl">

            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-user-edit text-blue-500"></i>{{ __('Edit Customer') }}
                </h3>
                <button @click="closeEditModal()"
                    class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form wire:submit.prevent="updateCustomer" class="p-5 space-y-4">

                {{-- Name --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-user mr-1"></i>
                        {{ __('Customer Name') }}
                        <span class="text-red-500 normal-case font-normal">*</span>
                    </label>

                    <input type="text" wire:model="name"
                           placeholder="{{ __('Enter customer name') }}"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('name') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Unit & Address --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        {{ __('Unit & Address') }}
                        <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" wire:model="unit"
                               placeholder="Unit 123"
                               class="w-28 px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                      bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                      focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                        <input type="text" wire:model="address"
                               placeholder="123 Sesame Street"
                               class="flex-1 px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                      bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                      focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    </div>
                    @error('unit')    <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                    @error('address') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                {{-- Contact Number --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-phone mr-1"></i>
                        {{ __('Contact Number') }}
                        <span class="text-gray-500 normal-case font-normal">*</span>
                    </label>

                    <input maxlength="11" type="tel" inputmode="numeric" pattern="[0-9]*"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           wire:model="contact_number"
                           placeholder="{{ __('Enter contact number (e.g., 09123456789)') }}"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                                  bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('contact_number') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeEditModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button type="submit"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                        <i class="fas fa-save mr-1"></i>{{ __('Update Customer') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         DELETE CONFIRM MODAL
    ════════════════════════════════════════════════ --}}
    <div x-show="showDeleteModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-sm bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Delete Customer') }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    {{ __('Are you sure you want to delete this customer? This action cannot be undone and will permanently remove all customer data.') }}
                </p>
                <div class="flex justify-center gap-2">
                    <button @click="closeDeleteModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button wire:click="deleteCustomer()"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-red-600 text-white hover:bg-red-700 active:scale-95 transition-all">
                        <i class="fas fa-trash mr-1"></i>{{ __('Confirm Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

<style>
    .cust-card-btn {
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

    {{-- Full-screen loading overlay for customer actions --}}
    @include('livewire.partials.loading-overlay', ['wireTarget' => 'search,sortByField,createCustomer,updateCustomer,deleteCustomer'])

</div>
