@section('title', __('Dashboard'))
<div class="container p-1 mx-auto">

    {{-- Header --}}
    <div class="flex flex-col gap-3 mb-6 md:flex-row md:items-center md:justify-between">
        <div>
                <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 fas fa-chart-bar"></i>{{ __('Dashboard') }}
            </h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __("Welcome back! Here's what's happening today.") }}</p>
        </div>
        @include('livewire.partials.clock')
    </div>

    {{-- KPI Overview --}}
    <div class="mb-6 space-y-4">
        <div>
            <h3 class="mb-2 text-sm font-semibold tracking-wide uppercase text-zinc-600 dark:text-zinc-300">{{ __('Sales KPIs') }}</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">

                {{-- 今日營收：所有人可見 --}}
                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($todayStats['income'] ?? 0, 2) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Revenue Today') }}</p>
                            @php $revTodayTrend = (float) ($todayStats['sales_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $revTodayTrend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $revTodayTrend >= 0 ? '+' : '' }}{{ number_format($revTodayTrend, 1) }}% {{ __('from yesterday') }}
                            </p>
                        </div>
                        <i class="text-lg fas fa-coins {{ $revTodayTrend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}"></i>
                    </div>
                </div>

                {{-- 月營收：僅管理員可見 --}}
                @if(auth()->user()->isAdmin())
                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($businessInsights['month_sales'] ?? 0, 2) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Revenue This Month') }}</p>
                            @php $revMonthTrend = (float) ($businessInsights['month_sales_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $revMonthTrend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $revMonthTrend >= 0 ? '+' : '' }}{{ number_format($revMonthTrend, 1) }}% {{ __('from previous period') }}
                            </p>
                        </div>
                        <i class="text-lg fas fa-chart-line {{ $revMonthTrend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}"></i>
                    </div>
                </div>
                @endif

                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($todayStats['orders'] ?? 0) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Orders Today') }}</p>
                            @php $ordersTrend = (float) ($todayStats['orders_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $ordersTrend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $ordersTrend >= 0 ? '+' : '' }}{{ number_format($ordersTrend, 1) }}% {{ __('from yesterday') }}
                            </p>
                        </div>
                        <i class="text-lg fas fa-basket-shopping {{ $ordersTrend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}"></i>
                    </div>
                </div>

                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($todayStats['avg_order'] ?? 0, 2) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Average Order Value') }}</p>
                            @php $aovTrend = (float) ($todayStats['avg_order_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $aovTrend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $aovTrend >= 0 ? '+' : '' }}{{ number_format($aovTrend, 1) }}% {{ __('from yesterday') }}
                            </p>
                        </div>
                        <i class="text-lg fas fa-receipt {{ $aovTrend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}"></i>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold tracking-wide uppercase text-zinc-600 dark:text-zinc-300">{{ __('Operational KPIs') }}</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['pending_orders'] ?? 0) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Pending Orders') }}</p>
                            @php $pendingTrend = (float) ($businessInsights['pending_orders_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $pendingTrend <= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $pendingTrend >= 0 ? '+' : '' }}{{ number_format($pendingTrend, 1) }}% {{ __('from previous period') }}
                            </p>
                        </div>
                        <i class="text-lg fas fa-hourglass-half {{ $pendingTrend <= 0 ? 'text-emerald-500' : 'text-rose-500' }}"></i>
                    </div>
                </div>

                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['low_stock_products'] ?? 0) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Low Stock Products') }}</p>
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">{{ number_format($businessInsights['low_stock_rate'] ?? 0, 1) }}% {{ __('of products') }}</p>
                        </div>
                        <i class="text-lg text-amber-500 fas fa-triangle-exclamation"></i>
                    </div>
                </div>

                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['out_of_stock_products'] ?? 0) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Out of Stock Products') }}</p>
                            <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ number_format($businessInsights['out_of_stock_rate'] ?? 0, 1) }}% {{ __('of products') }}</p>
                        </div>
                        <i class="text-lg text-rose-500 fas fa-box-open"></i>
                    </div>
                </div>

                <div class="p-5 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['active_customers'] ?? 0) }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Active Customers') }}</p>
                            @php $activeCustomersTrend = (float) ($businessInsights['active_customers_growth'] ?? 0); @endphp
                            <p class="mt-2 text-xs {{ $activeCustomersTrend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $activeCustomersTrend >= 0 ? '+' : '' }}{{ number_format($activeCustomersTrend, 1) }}% {{ __('from previous period') }}
                            </p>
                        </div>
                        <i class="text-lg {{ $activeCustomersTrend >= 0 ? 'text-emerald-500' : 'text-rose-500' }} fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Business Health and Operations (管理員專屬) --}}
    @if(auth()->user()->isAdmin())
    <div class="grid grid-cols-1 gap-3 mb-6 xl:grid-cols-2">
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-emerald-500 fas fa-coins"></i>{{ __('Money & Growth Snapshot') }}
                <span class="ml-2 text-xs font-normal text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full"><i class="fas fa-lock mr-1"></i>{{ __('Admin Only') }}</span>
            </h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/10">
                    <p class="text-xs uppercase text-emerald-700 dark:text-emerald-300">{{ __('Monthly Revenue') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($businessInsights['month_sales'] ?? 0, 2) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Last 30 days') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/10">
                    <p class="text-xs uppercase text-amber-700 dark:text-amber-300">{{ __('Estimated Profit') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($businessInsights['month_profit'] ?? 0, 2) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Based on product cost') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/10">
                    <p class="text-xs uppercase text-blue-700 dark:text-blue-300">{{ __('Average Order Value') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($businessInsights['average_order_value'] ?? 0, 2) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Per completed order') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-violet-50 dark:bg-violet-900/10">
                    <p class="text-xs uppercase text-violet-700 dark:text-violet-300">{{ __('Average Daily Sales') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">₱{{ number_format($businessInsights['average_daily_sales'] ?? 0, 2) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('30-day run rate') }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-orange-500 fas fa-box-open"></i>{{ __('Product Health & Operations') }}
            </h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ __('Products Sold') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['month_units_sold'] ?? 0) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Units moved in the last 30 days') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ __('Active Products') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['active_products'] ?? 0) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Currently in stock') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ __('Low Stock Items') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['low_stock_products'] ?? 0) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Need restocking soon') }}</p>
                </div>
                <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ __('Order Completion Rate') }}
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['completion_rate'] ?? 0, 1) }}%</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Delivered and completed orders') }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Top Selling Products Section --}}
    <div class="grid grid-cols-1 gap-3 mb-6 xl:grid-cols-3">

        {{-- Today's Best Sellers --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-yellow-500 fas fa-trophy"></i>{{ __('Today\'s Best Seller') }}
            </h3>
            @if(!empty($topSellingProducts['today']))
                <div class="space-y-3">
                    @foreach($topSellingProducts['today'] as $index => $product)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 bg-zinc-50/80 px-3 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <div class="flex items-center space-x-3 min-w-0">
                                <span class="flex items-center justify-center w-7 h-7 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ Str::limit($product['name'] ?? '', 18) }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __($product['category_label'] ?? __('Uncategorized')) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($product['total_sold'] ?? 0) }} {{ __('sold') }}</p>
                                @if(auth()->user()->isAdmin())
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">₱{{ number_format($product['total_revenue'] ?? 0, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <i class="mb-3 text-4xl fas fa-chart-line text-zinc-300 dark:text-zinc-600"></i>
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No sales recorded today') }}</p>
                </div>
            @endif
        </div>

        {{-- This Week's Best Sellers --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-blue-500 fas fa-chart-bar"></i>{{ __('This Week\'s Top Seller') }}
            </h3>

            @if(!empty($topSellingProducts['week']))
                <div class="space-y-3">
                    @foreach($topSellingProducts['week'] as $index => $product)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 bg-zinc-50/80 px-3 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <div class="flex items-center space-x-3 min-w-0">
                                <span class="flex items-center justify-center w-7 h-7 text-xs font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ Str::limit($product['name'] ?? '', 18) }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __($product['category_label'] ?? __('Uncategorized')) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($product['total_sold'] ?? 0) }} {{ __('units sold') }}</p>
                                @if(auth()->user()->isAdmin())
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">₱{{ number_format($product['total_revenue'] ?? 0, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <i class="mb-3 text-4xl fas fa-chart-line text-zinc-300 dark:text-zinc-600"></i>
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No sales recorded this week') }}</p>
                </div>
            @endif

        </div>

        {{-- Average Top Performers --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-purple-500 fas fa-medal"></i>{{ __("This Month's Top Sellers") }}
            </h3>

            @if(!empty($topSellingProducts['average']))
                <div class="space-y-3">
                    @foreach($topSellingProducts['average'] as $index => $product)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-100 bg-zinc-50/80 px-3 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <div class="flex items-center space-x-3">
                                <span class="flex items-center justify-center w-6 h-6 text-xs font-medium rounded-full bg-zinc-100 dark:bg-zinc-700">{{ $index + 1 }}</span>
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ Str::limit($product['name'] ?? '', 15) }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($product['avg_weekly'] ?? 0, 1) }}/{{ __('week') }}</span>
                                @if(auth()->user()->isAdmin())
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ number_format($product['total_sold'] ?? 0) }} {{ __('total') }} • ₱{{ number_format($product['total_revenue'] ?? 0, 2) }}</span>
                                @else
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ number_format($product['total_sold'] ?? 0) }} {{ __('total') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <i class="mb-3 text-4xl fas fa-chart-line text-zinc-300 dark:text-zinc-600"></i>
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No data available') }}</p>
                </div>
            @endif

        </div>
    </div>

    {{-- Descriptive Analytics Chart Section (管理員專屬) --}}
    @if(auth()->user()->isAdmin())
    <div class="grid grid-cols-1 gap-3 mb-8 lg:grid-cols-2">

        {{-- Store Trend --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-blue-500 fas fa-chart-line"></i>{{ __('Store Trend (Last 30 Days)') }}
            </h3>
            <div class="h-80" wire:ignore>
                <canvas id="salesVsProfitChart"></canvas>
            </div>
        </div>

        {{-- Orders by Day Chart --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-green-500 fas fa-calendar-alt"></i>{{ __('Orders by Day (Current vs Previous Week)') }}
            </h3>
            <div class="h-80" wire:ignore>
                <canvas id="ordersByDayChart"></canvas>
            </div>
        </div>

        {{-- Busiest / Most Profitable (Year / Month / Weekday / Hour) --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    <i class="mr-2 text-indigo-500 fas fa-chart-column"></i>{{ __('Peak Analytics') }}
                </h3>

                <div id="busiest-metrics-toggle" class="inline-flex p-1 rounded-lg bg-zinc-100 dark:bg-zinc-700/60">
                    <button type="button" data-busiest-target="year" aria-pressed="true" class="px-3 py-1.5 text-sm font-medium transition rounded-md bg-indigo-600 text-white dark:bg-indigo-500">
                        {{ __('Year') }}
                    </button>
                    <button type="button" data-busiest-target="month" aria-pressed="false" class="px-3 py-1.5 text-sm font-medium transition rounded-md text-zinc-700 dark:text-zinc-200">
                        {{ __('Month') }}
                    </button>
                    <button type="button" data-busiest-target="weekday" aria-pressed="false" class="px-3 py-1.5 text-sm font-medium transition rounded-md text-zinc-700 dark:text-zinc-200">
                        {{ __('Weekday') }}
                    </button>
                    <button type="button" data-busiest-target="hour" aria-pressed="false" class="px-3 py-1.5 text-sm font-medium transition rounded-md text-zinc-700 dark:text-zinc-200">
                        {{ __('Hour') }}
                    </button>
                </div>
            </div>

            <div data-busiest-panel="year">
                <div class="h-80" wire:ignore>
                    <canvas id="busiestByYearChart"></canvas>
                </div>
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div id="year-summary-most-orders"></div>
                    <div id="year-summary-most-profit"></div>
                </div>
            </div>

            <div data-busiest-panel="month" class="hidden">
                <div class="h-80" wire:ignore>
                    <canvas id="busiestByMonthChart"></canvas>
                </div>
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div id="month-summary-most-orders"></div>
                    <div id="month-summary-most-profit"></div>
                </div>
            </div>

            <div data-busiest-panel="weekday" class="hidden">
                <div class="h-80" wire:ignore>
                    <canvas id="busiestByWeekdayChart"></canvas>
                </div>
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div id="weekday-summary-most-orders"></div>
                    <div id="weekday-summary-most-profit"></div>
                </div>
            </div>

            <div data-busiest-panel="hour" class="hidden">
                <div class="h-80" wire:ignore>
                    <canvas id="busiestByHourChart"></canvas>
                </div>
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <div id="hour-summary-most-orders"></div>
                    <div id="hour-summary-most-profit"></div>
                </div>
            </div>
        </div>

        {{-- Category Breakdown --}}
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-orange-500 fas fa-chart-bar"></i>{{ __('Sales by Category (Last 30 Days)') }}
            </h3>
            <div class="h-94" wire:ignore>
                <canvas id="categoryBreakdownChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Insights (管理員專屬) --}}
    <div class="grid grid-cols-1 gap-3 mb-6 xl:grid-cols-3">
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 xl:col-span-2">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-indigo-500 fas fa-lightbulb"></i>{{ __('Insights') }}
            </h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Best Category') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Highest sales by category in the last 30 days') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __($businessInsights['top_category'] ?? __('No data')) }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">₱{{ number_format($businessInsights['top_category_sales'] ?? 0, 2) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Best Product This Month') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Most units sold in the last 30 days') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ Str::limit($businessInsights['top_product_name'] ?? 'No data', 26) }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($businessInsights['top_product_sales'] ?? 0) }} {{ __('units') }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Payment Rate') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Paid orders share over the last 30 days') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['payment_rate'] ?? 0, 1) }}%</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Cash flow health') }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Refund Rate') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Share of refunded orders in the last 30 days') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['refund_rate'] ?? 0, 1) }}%</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Lower is better') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="p-6 bg-white border rounded-lg shadow-sm dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                <i class="mr-2 text-red-500 fas fa-triangle-exclamation"></i>{{ __('Stock Watch') }}
            </h3>
            <div class="space-y-4">
                <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/10">
                    <p class="text-xs uppercase text-red-700 dark:text-red-300">{{ __('Low Stock') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['low_stock_products'] ?? 0) }}</p>
                </div>
                <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/40">
                    <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ __('Out of Stock') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($businessInsights['out_of_stock_products'] ?? 0) }}</p>
                </div>
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/10">
                    <p class="text-xs uppercase text-blue-700 dark:text-blue-300">{{ __('Units Sold') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($todayStats['units_sold'] ?? 0) }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today') }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

{{-- Add chart labels for JS --}}
<script>
  window.__dashboardI18n = {
      sales:                 "{{ __('Sales (₱)') }}",
      estimated_profit:      "{{ __('Estimated Profit (₱)') }}",
      orders:                "{{ __('Orders') }}",
      current_week:          "{{ __('Current Week') }}",
      previous_week:         "{{ __('Previous Week') }}",
      monthly_sales:         "{{ __('Monthly Sales (₱)') }}",
      monthly_orders:        "{{ __('Monthly Orders') }}",
      amount_currency:       "{{ __('Amount (₱)') }}",
      num_orders:            "{{ __('Number of Orders') }}",
      new_customers:         "{{ __('New Customers') }}",
      count_axis:            "{{ __('Orders / Customers') }}",
      sales_amount_currency: "{{ __('Sales Amount (₱)') }}",
      most_busy: "{{ __('Peak Activity') }}",
      most_profitable: "{{ __('Peak Profit') }}",
  };

  // Pass current locale to JavaScript
  window.__appLocale = "{{ app()->getLocale() }}";

  // Known category translations (fallback if backend didn't localize)
  window.__categoryMap = {
      "Meat & Poultry": "{{ __('Meat & Poultry') }}",
      "Vegetables": "{{ __('Vegetables') }}",
      "Fruits": "{{ __('Fruits') }}",
      "Dairy": "{{ __('Dairy') }}",
      "Eggs": "{{ __('Eggs') }}",
      "Seafood": "{{ __('Seafood') }}",
      "Beverages": "{{ __('Beverages') }}",
      "Snacks": "{{ __('Snacks') }}",
      "Condiments & Spices": "{{ __('Condiments & Spices') }}",
      "Grains & Cereals": "{{ __('Grains & Cereals') }}",
      "Frozen Goods": "{{ __('Frozen Goods') }}",
      "Bakery Goods": "{{ __('Bakery Goods') }}",
      "Gas": "{{ __('Gas') }}",
      "Other": "{{ __('Other') }}",
  };
</script>
</div>
