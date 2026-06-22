@section('title', __('Employee Management'))

<div class="w-full max-w-full overflow-x-hidden px-2 sm:px-4 pb-8"
     x-data="{
         showCreateModal: false,
         showEditModal: false,
         showDeleteModal: false,

         openCreateModal()    { this.showCreateModal = true;  $wire.resetForm(); },
         closeCreateModal()   { this.showCreateModal = false; $wire.resetForm(); },
         openEditModal(id)    { this.showEditModal   = true;  $wire.loadEmployeeForEdit(id); },
         closeEditModal()     { this.showEditModal   = false; $wire.resetForm(); },
         openDeleteModal(id)  { this.showDeleteModal = true;  $wire.setSelectedEmployee(id); },
         closeDeleteModal()   { this.showDeleteModal = false; $wire.setSelectedEmployee(null); },
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
                {{ __('Employee Management') }}
            </h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                {{ __('Manage delivery personnel and staff') }}
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('employees.archived') }}">
                <button class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-sm font-medium hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-colors">
                    <i class="fas fa-archive text-zinc-500 dark:text-zinc-400"></i>
                    <span class="hidden sm:inline">{{ __('View Archive') }}</span>
                    <span class="inline-flex items-center justify-center min-w-[1.3rem] h-5 px-1 text-xs font-bold rounded-full bg-zinc-200 dark:bg-zinc-600 text-zinc-600 dark:text-zinc-300">
                        {{ App\Models\Employee::where('is_archived', true)->count() }}
                    </span>
                </button>
            </a>
            <button @click="openCreateModal()"
                class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold
                       hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                <i class="fas fa-plus"></i>
                <span class="hidden sm:inline">{{ __('Create Employee') }}</span>
                <span class="sm:hidden">{{ __('Add') }}</span>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         STATS CARDS
    ════════════════════════════════════════════════ --}}
    @php
        $totalEmployees  = $allEmployees->count();
        $activeCount     = $allEmployees->where('status', 'active')->count();
        $inactiveCount   = $allEmployees->where('status', 'inactive')->count();
        $archivedCount   = \App\Models\Employee::where('is_archived', true)->count();
        $activePct       = $totalEmployees ? round(($activeCount / $totalEmployees) * 100) : 0;
        $inactivePct     = $totalEmployees ? round(($inactiveCount / $totalEmployees) * 100) : 0;
        $archivedPct     = $totalEmployees ? round(($archivedCount / $totalEmployees) * 100) : 0;

        $deliveredStatuses = ['delivered', 'completed'];
        $todayDelivered     = \App\Models\Order::whereIn('status', $deliveredStatuses)->whereDate('updated_at', now())->count();
        $totalDelivered     = \App\Models\Order::whereIn('status', $deliveredStatuses)->count();
        $yesterdayDelivered = \App\Models\Order::whereIn('status', $deliveredStatuses)->whereDate('updated_at', \Carbon\Carbon::yesterday())->count();
        $last7Delivered     = \App\Models\Order::whereIn('status', $deliveredStatuses)->where('updated_at', '>=', now()->subDays(7))->count();
        $last30Delivered    = \App\Models\Order::whereIn('status', $deliveredStatuses)->where('updated_at', '>=', now()->subDays(30))->count();
        $avgPerActive7      = $activeCount ? round($last7Delivered / $activeCount, 1) : 0.0;

        $topWeek  = \App\Models\Order::selectRaw('delivered_by, COUNT(*) as c')->whereIn('status', $deliveredStatuses)->where('updated_at', '>=', now()->subDays(7))->whereNotNull('delivered_by')->groupBy('delivered_by')->orderByDesc('c')->first();
        $topMonth = \App\Models\Order::selectRaw('delivered_by, COUNT(*) as c')->whereIn('status', $deliveredStatuses)->where('updated_at', '>=', now()->subDays(30))->whereNotNull('delivered_by')->groupBy('delivered_by')->orderByDesc('c')->first();
        $topWeekEmp  = $topWeek  ? \App\Models\Employee::find($topWeek->delivered_by)  : null;
        $topMonthEmp = $topMonth ? \App\Models\Employee::find($topMonth->delivered_by) : null;
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">

        {{-- Workforce breakdown --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0">
                    <i class="fas fa-users text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-none">{{ number_format($totalEmployees) }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Employees') }}</div>
                </div>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400"><i class="fas fa-circle text-green-500 mr-1 text-[8px]"></i>{{ __('Active') }}</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $activeCount }} <span class="font-normal text-zinc-400">({{ $activePct }}%)</span></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400"><i class="fas fa-circle text-yellow-500 mr-1 text-[8px]"></i>{{ __('Inactive') }}</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $inactiveCount }} <span class="font-normal text-zinc-400">({{ $inactivePct }}%)</span></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500 dark:text-zinc-400"><i class="fas fa-circle text-zinc-400 mr-1 text-[8px]"></i>{{ __('Archived') }}</span>
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $archivedCount }} <span class="font-normal text-zinc-400">({{ $archivedPct }}%)</span></span>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="mt-3 h-1.5 w-full bg-zinc-100 dark:bg-zinc-700 rounded-full overflow-hidden flex">
                <div class="bg-green-500 transition-all" style="width: {{ $activePct }}%"></div>
                <div class="bg-yellow-400 transition-all" style="width: {{ $inactivePct }}%"></div>
                <div class="bg-zinc-400 transition-all" style="width: {{ $archivedPct }}%"></div>
            </div>
        </div>

        {{-- Performance --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                    <i class="fas fa-chart-line text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">{{ __('Employee Performance') }}</div>
                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Delivered Orders') }}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-center sm:grid-cols-3 lg:grid-cols-5">
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-xl py-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($todayDelivered) }}</div>
                    <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('Today') }}</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-xl py-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($yesterdayDelivered) }}</div>
                    <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('Yesterday') }}</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-xl py-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($last7Delivered) }}</div>
                    <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('7 Days') }}</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-xl py-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($last30Delivered) }}</div>
                    <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('30 Days') }}</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-700/50 rounded-xl py-2">
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($totalDelivered) }}</div>
                    <div class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-0.5">{{ __('Total') }}</div>
                </div>
            </div>
            <p class="text-center text-xs text-zinc-400 dark:text-zinc-500 mt-2">
                {{ __('Average of') }} <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $avgPerActive7 }}</span> {{ __('orders per active employee') }}
            </p>
        </div>

        {{-- Top Performers --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                    <i class="fas fa-trophy text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div>
                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">{{ __('Top Performers') }}</div>
                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('By delivered count') }}</div>
                </div>
            </div>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fas fa-award text-amber-500 shrink-0 text-sm"></i>
                        <div class="min-w-0">
                            <p class="text-[10px] text-zinc-400 dark:text-zinc-500 uppercase tracking-wide">{{ __('Top Performer (Week)') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $topWeekEmp ? $topWeekEmp->name : __('N/A') }}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 text-xs font-bold rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 shrink-0">
                        {{ $topWeek->c ?? 0 }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fas fa-medal text-indigo-500 shrink-0 text-sm"></i>
                        <div class="min-w-0">
                            <p class="text-[10px] text-zinc-400 dark:text-zinc-500 uppercase tracking-wide">{{ __('Top Performer (Month)') }}</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $topMonthEmp ? $topMonthEmp->name : __('N/A') }}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 text-xs font-bold rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 shrink-0">
                        {{ $topMonth->c ?? 0 }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- SEARCH & FILTER --}}
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    <i class="fas fa-search mr-1"></i>{{ __('Search Employees') }}
                </label>
                <div class="relative">
                    <input type="text"
                           wire:model.live="search"
                           placeholder="{{ __('Search by name or contact number') }}"
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

            {{-- Status Filter --}}
            <div class="sm:w-48">
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    <i class="fas fa-filter mr-1"></i>{{ __('Filter by status') }}
                </label>
                <select wire:model.live="statusFilter"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600
                           bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100
                           focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition cursor-pointer">
                    <option value="">{{ __('All') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- EMPLOYEE LIST --}}

    {{-- ── Mobile Cards (< lg) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
        @forelse($employees as $employee)
            <div wire:key="mobile-emp-{{ $employee->id }}"
                 class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden">

                @php
                    $stripColor = match($employee->status) {
                        'active'   => 'bg-green-500',
                        'inactive' => 'bg-yellow-400',
                        default    => 'bg-zinc-300 dark:bg-zinc-600',
                    };
                @endphp
                <div class="h-1 w-full {{ $stripColor }}"></div>

                <div class="p-4 space-y-3">
                    {{-- Name + ID + Status --}}
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center shrink-0 shadow-sm">
                            <span class="text-white text-sm font-bold">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 truncate">#{{ $employee->id }} - {{ $employee->name }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500"><i class="fas fa-phone mr-0.5"></i>{{ $employee->contact_number ?: 'N/A' }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold shrink-0
                                     bg-{{ $employee->status_color }}-100 text-{{ $employee->status_color }}-800
                                     dark:bg-{{ $employee->status_color }}-900/30 dark:text-{{ $employee->status_color }}-300">
                            <i class="fas fa-circle text-[8px]"></i>{{ __(ucwords($employee->status)) }}
                        </span>
                    </div>

                    {{-- Info --}}
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-phone w-3.5 shrink-0 text-zinc-400"></i>
                            {{ $employee->contact_number ?: 'N/A' }}
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-box w-3.5 shrink-0 text-zinc-400"></i>
                            {{ $employee->orders_delivered ?: 0 }} {{ __('Total Deliveries') }}
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-box w-3.5 shrink-0 text-zinc-400"></i>
                            {{ $employee->orders_delivered_today ?: 0 }} {{ __('Deliveries Today') }}
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1.5 pt-1 border-t border-zinc-100 dark:border-zinc-700">
                        @if(auth()->check() && auth()->user()->isAdmin())
                            <button @click="openEditModal({{ $employee->id }})"
                                class="emp-card-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
                                <i class="fas fa-edit"></i>{{ __('Edit') }}
                            </button>
                            <button @click="openDeleteModal({{ $employee->id }})"
                                class="emp-card-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 ml-auto">
                                <i class="fas fa-archive"></i>{{ __('Archive') }}
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
                <p class="text-sm">{{ __('No employees found.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- ── Desktop Table (≥ lg) ── --}}
    <div class="hidden lg:block bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                    <tr>
                        <th wire:click="sortByField('name')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                {{ __('Employees') }}
                                @if($sortBy === 'name')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>

                        {{-- Delivered Today --}}
                        <th wire:click="sortByField('orders_delivered')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                {{ __('Delivered Today') }}
                                @if($sortBy === 'orders_delivered')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>

                        {{-- Total Deliveries --}}
                        <th wire:click="sortByField('orders_delivered')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                {{ __('Total Deliveries') }}
                                @if($sortBy === 'orders_delivered')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                @else
                                    <i class="fas fa-sort text-zinc-300 dark:text-zinc-600"></i>
                                @endif
                            </div>
                        </th>

                        {{-- Status --}}
                        <th wire:click="sortByField('status')"
                            class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 select-none">
                            <div class="flex items-center justify-center gap-1">
                                {{ __('Status') }}
                                @if($sortBy === 'status')
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
                    @forelse($employees as $employee)
                        <tr wire:key="desktop-emp-{{ $employee->id }}"
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40 transition-colors">

                            {{-- Name --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center shrink-0 shadow-sm">
                                        <span class="text-white text-xs font-bold">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 block">#{{ $employee->id }} - {{ $employee->name }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400"><i class="fas fa-phone mr-1"></i>{{ $employee->contact_number ?: 'N/A' }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Contact Number --}}
                            <td class="px-4 py-3 text-center text-sm text-zinc-700 dark:text-zinc-300 hidden">
                                <i class="fas fa-phone mr-1 text-zinc-400 text-xs"></i>{{ $employee->contact_number ?: 'N/A' }}
                            </td>

                            {{-- Delivered Today --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                             {{ ($employee->orders_delivered_today ?: 0) > 0
                                                 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                 : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}">
                                    <i class="fas fa-calendar-day text-[10px]"></i>{{ $employee->orders_delivered_today ?: 0 }}
                                </span>
                            </td>

                            {{-- Total Deliveries --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                             {{ ($employee->orders_delivered ?: 0) > 0
                                                 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                 : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }}">
                                    <i class="fas fa-box text-[10px]"></i>{{ $employee->orders_delivered ?: 0 }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                             bg-{{ $employee->status_color }}-100 text-{{ $employee->status_color }}-800
                                             dark:bg-{{ $employee->status_color }}-900/30 dark:text-{{ $employee->status_color }}-300">
                                    <i class="fas fa-circle text-[8px]"></i>{{ __(ucwords($employee->status)) }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @if(auth()->check() && auth()->user()->isAdmin())
                                        <button @click="openEditModal({{ $employee->id }})"
                                            class="tbl-action-btn text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20">
                                            <i class="fas fa-edit text-sm"></i>
                                            <span class="text-xs">{{ __('Edit') }}</span>
                                        </button>
                                        <button @click="openDeleteModal({{ $employee->id }})"
                                            class="tbl-action-btn text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                            <i class="fas fa-archive text-sm"></i>
                                            <span class="text-xs">{{ __('Archive') }}</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-zinc-400 italic px-2">{{ __('View Only') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center text-zinc-400 dark:text-zinc-500">
                                    <i class="fas fa-users text-5xl mb-4 opacity-40"></i>
                                    <p class="text-sm">{{ __('No employees found.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-700">
            {{ $employees->links() }}
        </div>
    </div>

    {{-- Mobile pagination --}}
    <div class="lg:hidden mt-3">{{ $employees->links() }}</div>


    {{-- ═══════════════════════════════════════════════
         CREATE EMPLOYEE MODAL
    ════════════════════════════════════════════════ --}}
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 z-50">
        <div class="bg-white dark:bg-zinc-800 w-full sm:rounded-2xl sm:max-w-lg max-h-[92dvh] overflow-y-auto shadow-2xl">
            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-user-plus text-blue-500"></i>{{ __('Create Employee') }}
                </h3>
                <button @click="closeCreateModal()"
                    class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form wire:submit.prevent="createEmployee" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-user mr-1"></i>{{ __('Employee Name') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <input type="text" wire:model="name" placeholder="{{ __('Enter employee name') }}"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('name') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-phone mr-1"></i>{{ __('Contact Number') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <input maxlength="11" type="tel" inputmode="numeric" pattern="[0-9]*"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           wire:model="contact_number"
                           placeholder="{{ __('Enter contact number (e.g., 09123456789)') }}"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('contact_number') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-circle mr-1"></i>{{ __('Status') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <select wire:model="status"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition cursor-pointer">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                    @error('status') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeCreateModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button type="submit"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                        <i class="fas fa-save mr-1"></i>{{ __('Create Employee') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         EDIT EMPLOYEE MODAL
    ════════════════════════════════════════════════ --}}
    <div x-show="showEditModal" x-cloak
         class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 z-50">
        <div class="bg-white dark:bg-zinc-800 w-full sm:rounded-2xl sm:max-w-lg max-h-[92dvh] overflow-y-auto shadow-2xl">
            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 z-10">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-user-edit text-blue-500"></i>{{ __('Edit Employee') }}
                </h3>
                <button @click="closeEditModal()"
                    class="cursor-pointer w-8 h-8 flex items-center justify-center rounded-full text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form wire:submit.prevent="updateEmployee" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-user mr-1"></i>{{ __('Employee Name') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <input type="text" wire:model="name" placeholder="{{ __('Enter employee name') }}"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('name') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-phone mr-1"></i>{{ __('Contact Number') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <input maxlength="11" type="tel" inputmode="numeric" pattern="[0-9]*"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           wire:model="contact_number"
                           placeholder="{{ __('Enter contact number (e.g., 09123456789)') }}"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    @error('contact_number') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                        <i class="fas fa-circle mr-1"></i>{{ __('Status') }} <span class="text-red-500 normal-case font-normal">*</span>
                    </label>
                    <select wire:model="status"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition cursor-pointer">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                    @error('status') <p class="text-red-500 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeEditModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button type="submit"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20">
                        <i class="fas fa-save mr-1"></i>{{ __('Update Employee') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         ARCHIVE CONFIRM MODAL
    ════════════════════════════════════════════════ --}}
    <div x-show="showDeleteModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-sm bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-archive text-orange-600 dark:text-orange-400 text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Archive Employee') }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                    {{ __('Are you sure you want to archive this employee? Archived employees can be restored later and their order history will be preserved.') }}
                </p>
                <div class="flex justify-center gap-2">
                    <button @click="closeDeleteModal()"
                        class="cursor-pointer px-4 py-2 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button wire:click="deleteEmployee"
                        class="cursor-pointer px-4 py-2 text-sm font-semibold rounded-xl bg-orange-600 text-white hover:bg-orange-700 active:scale-95 transition-all">
                        <i class="fas fa-archive mr-1"></i>{{ __('Confirm Archive') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

<style>
    .emp-card-btn {
        display: inline-flex; align-items: center; gap: 0.25rem;
        padding: 0.375rem 0.625rem; border-radius: 0.625rem;
        font-size: 0.75rem; font-weight: 500;
        transition: background-color 0.15s; cursor: pointer; white-space: nowrap;
    }
    .tbl-action-btn {
        display: inline-flex; flex-direction: column; align-items: center; gap: 0.125rem;
        padding: 0.375rem 0.5rem; border-radius: 0.5rem;
        font-size: 0.75rem; font-weight: 500;
        transition: background-color 0.15s; cursor: pointer; white-space: nowrap;
        min-width: 3rem; text-align: center;
    }
</style>

    {{-- Full-screen loading overlay for employee actions --}}
    @include('livewire.partials.loading-overlay', ['wireTarget' => 'search,statusFilter,sortByField,createEmployee,updateEmployee,deleteEmployee,restoreEmployee'])
</div>
