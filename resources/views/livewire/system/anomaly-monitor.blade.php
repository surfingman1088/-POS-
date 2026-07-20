<x-layouts.app>
<div class="flex h-full w-full flex-col gap-6 p-6">

    {{-- 頁面標題 --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span class="text-2xl">🛡️</span> 異常操作監控
            </h1>
            <p class="text-sm text-slate-400 mt-1">即時監控員工操作，偵測異常行為並推播通知</p>
        </div>
        <div class="text-xs text-slate-500">自動刷新：每 60 秒</div>
    </div>

    {{-- 統計卡片 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-4">
            <div class="text-slate-400 text-xs mb-1">今日異常總計</div>
            <div class="text-3xl font-bold text-white">{{ $this->stats['total_today'] }}</div>
        </div>
        <div class="bg-red-950/40 border border-red-700/40 rounded-xl p-4">
            <div class="text-red-400 text-xs mb-1">今日高風險</div>
            <div class="text-3xl font-bold text-red-400">{{ $this->stats['high_today'] }}</div>
        </div>
        <div class="bg-amber-950/40 border border-amber-700/40 rounded-xl p-4">
            <div class="text-amber-400 text-xs mb-1">待處理異常</div>
            <div class="text-3xl font-bold text-amber-400">{{ $this->stats['unresolved'] }}</div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-4">
            <div class="text-slate-400 text-xs mb-1">未推播通知</div>
            <div class="text-3xl font-bold text-slate-300">{{ $this->stats['unnotified'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- 左側：異常列表 --}}
        <div class="lg:col-span-2 flex flex-col gap-4">

            {{-- 篩選列 --}}
            <div class="flex flex-wrap gap-3 items-center bg-slate-800/40 rounded-xl p-3">
                <select wire:model.live="filterLevel"
                        class="bg-slate-700 border border-slate-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">全部等級</option>
                    <option value="high">🔴 高風險</option>
                    <option value="medium">🟡 中風險</option>
                    <option value="low">🔵 低風險</option>
                </select>
                <select wire:model.live="filterUser"
                        class="bg-slate-700 border border-slate-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">全部員工</option>
                    @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterResolved"
                        class="bg-slate-700 border border-slate-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="0">未處理</option>
                    <option value="1">已處理</option>
                    <option value="">全部</option>
                </select>
                <input wire:model.live="dateFrom" type="date"
                       class="bg-slate-700 border border-slate-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-slate-400 text-sm">至</span>
                <input wire:model.live="dateTo" type="date"
                       class="bg-slate-700 border border-slate-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            {{-- 異常列表 --}}
            <div class="space-y-2">
                @forelse ($anomalies as $log)
                <div class="bg-slate-800/50 border rounded-xl p-4 hover:bg-slate-800/70 transition cursor-pointer
                    {{ $log->level === 'high' ? 'border-red-700/50' : ($log->level === 'medium' ? 'border-amber-700/50' : 'border-slate-700/40') }}"
                     wire:click="viewDetail({{ $log->id }})">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            {{-- 等級指示燈 --}}
                            <div class="mt-1 flex-shrink-0">
                                @if ($log->level === 'high')
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                                @elseif ($log->level === 'medium')
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                @else
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-white">{{ $log->title }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                        {{ $log->level === 'high' ? 'bg-red-500/20 text-red-400' : ($log->level === 'medium' ? 'bg-amber-500/20 text-amber-400' : 'bg-blue-500/20 text-blue-400') }}">
                                        {{ $log->level_label }}
                                    </span>
                                    @if ($log->resolved)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-medium">已處理</span>
                                    @endif
                                    @if (!$log->notified && $log->level !== 'low')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-600/50 text-slate-400 font-medium">未推播</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-400 mt-1 truncate">{{ $log->detail }}</p>
                                <div class="flex items-center gap-3 mt-1.5 text-xs text-slate-500">
                                    <span>👤 {{ $log->user?->name ?? '未知員工' }}</span>
                                    <span>🏪 {{ $log->store_name }}</span>
                                    <span>🕐 {{ $log->created_at->setTimezone('Asia/Taipei')->format('m/d H:i') }}</span>
                                </div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-500 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </div>
                @empty
                <div class="text-center py-16 text-slate-500">
                    <div class="text-4xl mb-3">✅</div>
                    <div class="text-sm">目前沒有符合條件的異常記錄</div>
                </div>
                @endforelse
            </div>

            <div class="text-slate-400">
                {{ $anomalies->links() }}
            </div>
        </div>

        {{-- 右側：今日員工行為摘要 --}}
        <div class="flex flex-col gap-4">
            <div class="bg-slate-800/50 border border-slate-700/40 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                    <span>👥</span> 今日員工行為摘要
                </h3>
                <div class="space-y-2">
                    @forelse ($this->employeeSummary as $summary)
                    <div class="flex items-center justify-between py-2 border-b border-slate-700/30 last:border-0">
                        <div>
                            <div class="text-sm text-white font-medium">{{ $summary['user']?->name ?? '未知' }}</div>
                            <div class="text-xs text-slate-500">
                                最後操作：{{ \Carbon\Carbon::parse($summary['last_action'])->setTimezone('Asia/Taipei')->format('H:i') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-right">
                            <div>
                                <div class="text-sm text-slate-300">{{ $summary['action_count'] }} 次操作</div>
                                @if ($summary['anomaly_count'] > 0)
                                <div class="text-xs text-red-400 font-medium">{{ $summary['anomaly_count'] }} 筆異常</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6 text-slate-500 text-sm">今日尚無員工操作記錄</div>
                    @endforelse
                </div>
            </div>

            {{-- 異常類型分佈 --}}
            <div class="bg-slate-800/50 border border-slate-700/40 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                    <span>📊</span> 近 7 日異常類型
                </h3>
                @php
                    $typeStats = \App\Models\AnomalyLog::where('created_at', '>=', now()->subDays(7))
                        ->select('anomaly_type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                        ->groupBy('anomaly_type')
                        ->orderByDesc('cnt')
                        ->limit(8)
                        ->get();
                    $typeLabels = [
                        'order.deleted'              => '訂單刪除',
                        'order.refunded'             => '大額退款',
                        'order.cancel_burst'         => '大量取消',
                        'order.large_discount'       => '大額折扣',
                        'order.large_amount'         => '大額訂單',
                        'auth.brute_force'           => '暴力破解',
                        'account.deleted'            => '帳號刪除',
                        'product.deleted'            => '商品刪除',
                        'product.stock_large_adjust' => '庫存大調',
                        'off_hour_operation'         => '非上班操作',
                    ];
                @endphp
                <div class="space-y-2">
                    @forelse ($typeStats as $stat)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-400">{{ $typeLabels[$stat->anomaly_type] ?? $stat->anomaly_type }}</span>
                        <span class="text-white font-medium">{{ $stat->cnt }}</span>
                    </div>
                    @empty
                    <div class="text-center py-4 text-slate-500 text-sm">近 7 日無異常記錄</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 詳情 Modal ── --}}
@if ($showDetail && $viewingLog)
<div class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                @if ($viewingLog->level === 'high')
                    <span class="text-red-400">🚨</span>
                @elseif ($viewingLog->level === 'medium')
                    <span class="text-amber-400">⚠️</span>
                @else
                    <span class="text-blue-400">ℹ️</span>
                @endif
                {{ $viewingLog->title }}
            </h2>
            <button wire:click="$set('showDetail', false)" class="text-slate-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            {{-- 基本資訊 --}}
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="text-slate-500 text-xs mb-1">員工</div>
                    <div class="text-white font-medium">{{ $viewingLog->user?->name ?? '未知' }}</div>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="text-slate-500 text-xs mb-1">分店</div>
                    <div class="text-white font-medium">{{ $viewingLog->store_name }}</div>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="text-slate-500 text-xs mb-1">時間</div>
                    <div class="text-white font-medium">{{ $viewingLog->created_at->setTimezone('Asia/Taipei')->format('Y/m/d H:i:s') }}</div>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <div class="text-slate-500 text-xs mb-1">等級</div>
                    <div class="font-medium {{ $viewingLog->level === 'high' ? 'text-red-400' : ($viewingLog->level === 'medium' ? 'text-amber-400' : 'text-blue-400') }}">
                        {{ $viewingLog->level_label }}
                    </div>
                </div>
            </div>

            {{-- 詳細說明 --}}
            <div class="bg-slate-800/50 rounded-lg p-3">
                <div class="text-slate-500 text-xs mb-1">異常說明</div>
                <div class="text-white text-sm">{{ $viewingLog->detail }}</div>
            </div>

            {{-- 原始 Audit Log --}}
            @if ($viewingLog->auditLog)
            <div class="bg-slate-800/50 rounded-lg p-3">
                <div class="text-slate-500 text-xs mb-2">操作原始記錄</div>
                <div class="text-xs font-mono text-slate-300 space-y-1">
                    <div><span class="text-slate-500">動作：</span>{{ $viewingLog->auditLog->action }}</div>
                    <div><span class="text-slate-500">IP：</span>{{ $viewingLog->auditLog->ip_address }}</div>
                    @if ($viewingLog->auditLog->new_values)
                    <div><span class="text-slate-500">新值：</span>{{ json_encode($viewingLog->auditLog->new_values, JSON_UNESCAPED_UNICODE) }}</div>
                    @endif
                </div>
            </div>
            @endif

            {{-- 處理備註 --}}
            @if (!$viewingLog->resolved)
            <div>
                <label class="block text-sm text-slate-400 mb-1">處理備註（選填）</label>
                <textarea wire:model="resolveNote" rows="2"
                          class="w-full px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                          placeholder="記錄處理方式或原因..."></textarea>
            </div>
            @else
            @if ($viewingLog->resolve_note)
            <div class="bg-emerald-950/30 border border-emerald-700/30 rounded-lg p-3">
                <div class="text-emerald-400 text-xs mb-1">處理備註</div>
                <div class="text-slate-300 text-sm">{{ $viewingLog->resolve_note }}</div>
            </div>
            @endif
            @endif
        </div>
        <div class="flex justify-between gap-3 px-6 py-4 border-t border-slate-700">
            <button wire:click="resendNotify({{ $viewingLog->id }})"
                    class="flex items-center gap-1.5 px-3 py-2 text-sm text-slate-300 hover:text-white border border-slate-600 hover:border-slate-500 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                重新推播
            </button>
            <div class="flex gap-2">
                <button wire:click="$set('showDetail', false)"
                        class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">關閉</button>
                @if (!$viewingLog->resolved)
                <button wire:click="markResolved({{ $viewingLog->id }})"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition">
                    ✓ 標記已處理
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- 自動刷新 --}}
<script>
    setTimeout(() => window.location.reload(), 60000);
</script>

</x-layouts.app>
