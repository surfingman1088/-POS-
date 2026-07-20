@section('title', __('Accounts & Sessions'))

{{-- ═══════════════════════════════════════════════════════════════════════════
     Shared helpers
     ──────────────────────────────────────────────────────────────────────── --}}
@php
    $sessionExpireDays = config('app.session_expire_days', 7);

    /**
     * Return a Tailwind colour token for an expiry date.
     * green  → more than 3 days left
     * yellow → 1–3 days left
     * red    → expires within 24 h or already expired
     */
    $expiryColour = function (?string $iso) use ($sessionExpireDays): string {
        if (! $iso) return 'zinc';
        $dt   = \Carbon\Carbon::parse($iso);
        $diff = now()->diffInHours($dt, false); // negative = expired
        if ($diff < 0)   return 'rose';
        if ($diff < 24)  return 'rose';
        if ($diff < 72)  return 'amber';
        return 'emerald';
    };
@endphp

<div class="w-full max-w-full overflow-x-hidden px-2 sm:px-4 pb-8">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between mb-5">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <i class="fas fa-users-gear text-blue-500"></i>
                {{ __('Accounts, Devices & Sessions') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Create accounts, review devices, and manage active sessions from one admin view.') }}
            </p>
        </div>
        <div class="flex-shrink-0">
            <button type="button" wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                <i class="fas fa-user-plus"></i>
                {{ __('Create account') }}
            </button>
        </div>
    </div>

    {{-- ── Filter + Metrics ────────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-4 shadow-sm mb-5">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide mb-1.5">
                    {{ __('Filter by user') }}
                </label>
                <select wire:model.live="userFilter"
                        class="w-full px-3 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition">
                    <option value="all">{{ __('All Users') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ([
                    ['label' => __('Accounts'), 'value' => $metrics['accounts'] ?? 0,     'colour' => 'zinc'],
                    ['label' => __('Active'),   'value' => $metrics['active_users'] ?? 0, 'colour' => 'blue'],
                    ['label' => __('Sessions'), 'value' => $metrics['sessions'] ?? 0,     'colour' => 'emerald'],
                    ['label' => __('Devices'),  'value' => $metrics['devices'] ?? 0,      'colour' => 'indigo'],
                ] as $metric)
                    <div class="rounded-xl border border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/30 p-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold
                            @if ($metric['colour'] === 'blue')    text-blue-600
                            @elseif ($metric['colour'] === 'emerald') text-emerald-600
                            @elseif ($metric['colour'] === 'indigo')  text-indigo-600
                            @else text-zinc-900 dark:text-zinc-100
                            @endif">
                            {{ number_format($metric['value']) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- USER ACCOUNTS --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden mb-4">

        {{-- Section header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-users text-blue-400 text-sm"></i>
                    {{ __('User Accounts') }}
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ __('Activity, session counts, and device coverage') }}
                </p>
            </div>
        </div>

        {{-- ── Desktop table ── --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-700/40 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide border-b border-zinc-100 dark:border-zinc-700">
                        <th class="text-center px-4 py-3">{{ __('User') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Activity') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Devices') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Sessions') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Last Login') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($accounts as $account)
                        @php $lastLogin = $account['last_login_at'] ? \Carbon\Carbon::parse($account['last_login_at']) : null; @endphp
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-700/30 transition-colors">

                            {{-- User cell --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase">
                                            {{ substr($account['name'], 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $account['name'] }}</div>
                                            @if (! empty($account['is_online']))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                                    <i class="fas fa-circle text-[6px]"></i>{{ __('Online') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 dark:bg-zinc-800/30 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">
                                                    <i class="fas fa-circle text-[6px]"></i>{{ __('Offline') }}
                                                </span>
                                            @endif
                                            {{-- 角色標籤 --}}
                                            @php $role = $account['role'] ?? 'admin'; @endphp
                                            @if($role === 'admin')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                                                    <i class="fas fa-crown text-[9px]"></i>{{ __('Admin') }}
                                                </span>
                                            @elseif($role === 'warehouse')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:text-green-300">
                                                    <i class="fas fa-warehouse text-[9px]"></i>倉庫管理員
                                                </span>
                                            @elseif($role === 'branch')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-900/20 px-2 py-0.5 text-[11px] font-semibold text-purple-700 dark:text-purple-300">
                                                    <i class="fas fa-store text-[9px]"></i>分店人員
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:text-blue-300">
                                                    <i class="fas fa-user text-[9px]"></i>{{ __('Staff') }}
                                                </span>
                                            @endif
                                            {{-- 待修改密碼標籤 --}}
                                            @if(!empty($account['must_change_password']))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-900/20 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:text-rose-300">
                                                    <i class="fas fa-key text-[9px]"></i>{{ __('Password not set') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">{{ $account['username'] }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Stats --}}
                            <td class="text-center px-4 py-3 text-zinc-700 dark:text-zinc-300 font-medium">
                                {{ number_format($account['actions_count']) }}
                            </td>

                            <td class="text-center px-4 py-3 text-zinc-700 dark:text-zinc-300 font-medium">
                                {{ number_format($account['device_count']) }}
                            </td>

                            <td class="text-center px-4 py-3 text-zinc-700 dark:text-zinc-300 font-medium">
                                {{ number_format($account['session_count']) }}
                            </td>

                            {{-- Last Login --}}
                            <td class="text-center px-4 py-3 whitespace-nowrap">
                                @if ($lastLogin)
                                    <span class="text-zinc-800 dark:text-zinc-100 text-xs font-medium">{{ $lastLogin->translatedFormat('M d, Y') }}</span>
                                    <span class="block text-zinc-400 text-xs">{{ $lastLogin->format('h:i:s A') }}</span>
                                @else
                                    <span class="text-zinc-400 text-xs">{{ __('Never') }}</span>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="px-4 py-3 text-center">
                                <button type="button" wire:click="confirmDeleteAccount({{ $account['user_id'] }})"
                                        class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                                    <i class="fas fa-trash-can mr-1.5"></i>{{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-400">
                                <i class="fas fa-users-slash mb-2 block text-2xl"></i>
                                {{ __('No accounts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Mobile cards ── --}}
        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-700">
            @forelse ($accounts as $account)
                @php $lastLogin = $account['last_login_at'] ? \Carbon\Carbon::parse($account['last_login_at']) : null; @endphp
                <div class="px-4 py-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase">{{ substr($account['name'], 0, 1) }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $account['name'] }}</p>
                                    @if (! empty($account['is_online']))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                            <i class="fas fa-circle text-[6px]"></i>{{ __('Online') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 dark:bg-zinc-800/30 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">
                                            <i class="fas fa-circle text-[6px]"></i>{{ __('Offline') }}
                                        </span>
                                    @endif
                                    @php $role = $account['role'] ?? 'admin'; @endphp
                                    @if($role === 'admin')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                                            <i class="fas fa-crown text-[9px]"></i>{{ __('Admin') }}
                                        </span>
                                    @elseif($role === 'warehouse')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:text-green-300">
                                            <i class="fas fa-warehouse text-[9px]"></i>倉庫管理員
                                        </span>
                                    @elseif($role === 'branch')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 dark:bg-purple-900/20 px-2 py-0.5 text-[11px] font-semibold text-purple-700 dark:text-purple-300">
                                            <i class="fas fa-store text-[9px]"></i>分店人員
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:text-blue-300">
                                            <i class="fas fa-user text-[9px]"></i>{{ __('Staff') }}
                                        </span>
                                    @endif
                                    @if(!empty($account['must_change_password']))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-900/20 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:text-rose-300">
                                            <i class="fas fa-key text-[9px]"></i>{{ __('Password not set') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500">{{ $account['username'] }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="confirmDeleteAccount({{ $account['user_id'] }})"
                                class="flex-shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                            <i class="fas fa-trash-can mr-1"></i> {{ __('Delete') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach ([
                            ['label' => __('Activity'), 'value' => number_format($account['actions_count'])],
                            ['label' => __('Devices'),  'value' => number_format($account['device_count'])],
                            ['label' => __('Sessions'), 'value' => number_format($account['session_count'])],
                            ['label' => __('Last Login'),'value' => $lastLogin ? $lastLogin->translatedFormat('M d, h:i A') : __('Never')],
                        ] as $cell)
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                                <div class="text-zinc-500 uppercase tracking-wide">{{ $cell['label'] }}</div>
                                <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ $cell['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-zinc-400">
                    <i class="fas fa-users-slash mb-2 block text-2xl"></i>
                    {{ __('No accounts found.') }}
                </div>
            @endforelse
        </div>
    </div>


    {{-- REMEMBERED DEVICES --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden mb-4">

        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-laptop text-indigo-400 text-sm"></i>
                    {{ __('Remembered Devices') }}
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ __('Trusted devices with active remember-me tokens · expire after') }}
                    <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $sessionExpireDays . __('days') }}</span>
                </p>
            </div>
        </div>

        {{-- ── Desktop table ── --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-700/40 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide border-b border-zinc-100 dark:border-zinc-700">
                        <th class="text-center px-4 py-3">{{ __('User') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Device') }}</th>
                        <th class="text-center px-4 py-3">{{ __('IP') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Last Used') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Expires') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($devices as $device)
                            @php
                            $lastUsed  = $device['last_used_at'] ? \Carbon\Carbon::parse($device['last_used_at']) : null;
                            $expiresAt = $device['expires_at']   ? \Carbon\Carbon::parse($device['expires_at'])   : null;
                            $eColour   = $expiryColour($device['expires_at']);
                        @endphp
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-700/30 transition-colors">

                            {{-- User --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                            {{ substr($device['user_name'] ?? '?', 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $device['user_name'] ?? __('System') }}</div>
                                        @if ($device['is_current'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 dark:text-indigo-300">
                                                <i class="fas fa-circle text-[6px]"></i>{{ __('This device') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Device info --}}
                            <td class="text-center px-4 py-3">
                                <div class="flex items-center justify-center gap-1.5 text-zinc-700 dark:text-zinc-200">
                                    @if (($device['device_type'] ?? '') === 'mobile')
                                        <i class="fas fa-mobile-screen-button text-zinc-400 w-4 text-center"></i>
                                    @elseif (($device['device_type'] ?? '') === 'tablet')
                                        <i class="fas fa-tablet-screen-button text-zinc-400 w-4 text-center"></i>
                                    @else
                                        <i class="fas fa-desktop text-zinc-400 w-4 text-center"></i>
                                    @endif
                                    <div class="text-left">
                                        <div class="font-medium text-xs">{{ $device['browser'] ?? __('Saved device') }}</div>
                                        <div class="text-xs text-zinc-400">{{ $device['platform'] ?? ucfirst($device['device_type'] ?? __('unknown')) }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- IP --}}
                            <td class="text-center px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">
                                {{ $device['ip_address'] ?? __('N/A') }}
                            </td>

                            {{-- Last Used --}}
                            <td class="text-center px-4 py-3 whitespace-nowrap">
                                @if ($lastUsed)
                                    <span class="text-zinc-800 dark:text-zinc-100 text-xs font-medium">{{ $lastUsed->translatedFormat('M d, Y') }}</span>
                                    <span class="block text-zinc-400 text-xs">{{ $lastUsed->format('h:i:s A') }}</span>
                                @else
                                    <span class="text-zinc-400 text-xs">{{ __('Never') }}</span>
                                @endif
                            </td>

                            {{-- Expires --}}
                            <td class="text-center px-4 py-3 whitespace-nowrap">
                                @if ($expiresAt)
                                    <span class="text-xs font-medium
                                        @if ($eColour === 'rose')    text-rose-600 dark:text-rose-400
                                        @elseif ($eColour === 'amber')   text-amber-600 dark:text-amber-400
                                        @else text-emerald-600 dark:text-emerald-400
                                        @endif">
                                        {{ $expiresAt->translatedFormat('M d, Y') }}
                                    </span>
                                    <span class="block text-zinc-400 text-xs">{{ $expiresAt->format('h:i A') }}</span>
                                    <span class="block text-[11px] font-semibold
                                        @if ($eColour === 'rose')    text-rose-500
                                        @elseif ($eColour === 'amber')   text-amber-500
                                        @else text-emerald-500
                                        @endif">
                                        {{ now()->gt($expiresAt) ? __('Expired') : $expiresAt->diffForHumans(['parts' => 1]) }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 text-xs">{{ __('Never') }}</span>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="text-center px-4 py-3">
                                <button type="button" wire:click="removeDevice({{ $device['id'] }})"
                                        class="inline-flex items-center rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    <i class="fas fa-xmark mr-1.5"></i>{{ __('Remove') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-400">
                                <i class="fas fa-laptop-medical mb-2 block text-2xl"></i>
                                {{ __('No remembered devices found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Mobile cards ── --}}
        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-700">
            @forelse ($devices as $device)
                @php
                    $lastUsed  = $device['last_used_at'] ? \Carbon\Carbon::parse($device['last_used_at']) : null;
                    $expiresAt = $device['expires_at']   ? \Carbon\Carbon::parse($device['expires_at'])   : null;
                    $eColour   = $expiryColour($device['expires_at']);
                @endphp
                <div class="px-4 py-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $device['user_name'] }}</p>
                                @if ($device['is_current'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 dark:text-indigo-300">
                                        <i class="fas fa-circle text-[6px]"></i>{{ __('This device') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ ($device['browser'] ?? __('Saved device')) }}{{ $device['platform'] ? ' · ' . $device['platform'] : '' }} · {{ ucfirst($device['device_type'] ?? __('unknown')) }}</p>
                        </div>
                        <button type="button" wire:click="removeDevice({{ $device['id'] }})"
                                class="flex-shrink-0 rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            <i class="fas fa-xmark mr-1"></i>{{ __('Remove') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                            <div class="text-zinc-500 uppercase tracking-wide">{{ __('IP') }}</div>
                            <div class="mt-1 font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $device['ip_address'] ?? __('N/A') }}</div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                            <div class="text-zinc-500 uppercase tracking-wide">{{ __('Last Used') }}</div>
                            <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ $lastUsed ? $lastUsed->translatedFormat('M d, h:i A') : __('Never') }}</div>
                        </div>
                        <div class="col-span-2 rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                            <div class="text-zinc-500 uppercase tracking-wide">{{ __('Expires') }}</div>
                            @if ($expiresAt)
                                <div class="mt-1 font-semibold
                                    @if ($eColour === 'rose')    text-rose-600 dark:text-rose-400
                                    @elseif ($eColour === 'amber')   text-amber-600 dark:text-amber-400
                                    @else text-emerald-600 dark:text-emerald-400
                                    @endif">
                                    {{ $expiresAt->translatedFormat('M d, Y h:i A') }}
                                    <span class="font-normal text-zinc-400">({{ now()->gt($expiresAt) ? __('Expired') : $expiresAt->diffForHumans(['parts' => 1]) }})</span>
                                </div>
                            @else
                                <div class="mt-1 text-zinc-400">—</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-zinc-400">
                    <i class="fas fa-laptop-medical mb-2 block text-2xl"></i>
                    {{ __('No remembered devices found.') }}
                </div>
            @endforelse
        </div>
    </div>


    {{-- ACTIVE SESSIONS --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 shadow-sm overflow-hidden mb-4">

        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <i class="fas fa-satellite-dish text-emerald-400 text-sm"></i>
                    {{ __('Active Sessions') }}
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ __('Currently active database sessions · idle sessions expire after') }}
                    <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $sessionExpireDays . __('days') }}</span>
                </p>
            </div>
        </div>

        {{-- ── Desktop table ── --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-zinc-50 dark:bg-zinc-700/40 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide border-b border-zinc-100 dark:border-zinc-700">
                        <th class="text-center px-4 py-3">{{ __('User') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Device') }}</th>
                        <th class="text-center px-4 py-3">{{ __('IP') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Last Seen') }}</th>
                        <th class="text-center px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @forelse ($sessions as $session)
                        @php $lastSeen = $session['last_seen_at'] ? \Carbon\Carbon::parse($session['last_seen_at']) : null; @endphp
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-700/30 transition-colors">

                            {{-- User --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase">
                                            {{ substr($session['user_name'] ?? '?', 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $session['user_name'] ?? __('System') }}</div>
                                            @if (! empty($session['is_online']))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                                    <i class="fas fa-circle text-[6px]"></i>{{ __('Online') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 dark:bg-zinc-800/30 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">
                                                    <i class="fas fa-circle text-[6px]"></i>{{ __('Offline') }}
                                                </span>
                                            @endif
                                        </div>
                                        @if ($session['is_current'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                                <i class="fas fa-circle text-[6px]"></i>{{ __('Current') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Device --}}
                            <td class="text-center px-4 py-3">
                                <div class="flex items-center justify-center gap-1.5 text-zinc-700 dark:text-zinc-200">
                                    @if (($session['device_type'] ?? '') === 'mobile')
                                        <i class="fas fa-mobile-screen-button text-zinc-400 w-4 text-center"></i>
                                    @elseif (($session['device_type'] ?? '') === 'tablet')
                                        <i class="fas fa-tablet-screen-button text-zinc-400 w-4 text-center"></i>
                                    @else
                                        <i class="fas fa-desktop text-zinc-400 w-4 text-center"></i>
                                    @endif
                                    <div class="text-left">
                                        <div class="font-medium text-xs">{{ $session['browser'] ?? ucfirst($session['device_type'] ?? __('unknown')) }}</div>
                                        <div class="text-xs text-zinc-400">{{ $session['platform'] ?? '' }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- IP --}}
                            <td class="text-center px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">
                                {{ $session['ip_address'] ?? __('N/A') }}
                            </td>

                            {{-- Last Seen --}}
                            <td class="text-center px-4 py-3 whitespace-nowrap">
                                @if ($lastSeen)
                                    <span class="text-zinc-800 dark:text-zinc-100 text-xs font-medium">{{ $lastSeen->translatedFormat('M d, Y') }}</span>
                                    <span class="block text-zinc-400 text-xs">{{ $lastSeen->format('h:i:s A') }}</span>
                                @else
                                    <span class="text-zinc-400 text-xs">—</span>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="text-center px-4 py-3">
                                <button type="button" wire:click="revokeSession('{{ $session['id'] }}')"
                                        class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                                    <i class="fas fa-right-from-bracket mr-1.5"></i>{{ __('Log Out') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-zinc-400">
                                <i class="fas fa-satellite mb-2 block text-2xl"></i>
                                {{ __('No active sessions found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Mobile cards ── --}}
        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-700">
            @forelse ($sessions as $session)
                @php $lastSeen = $session['last_seen_at'] ? \Carbon\Carbon::parse($session['last_seen_at']) : null; @endphp
                <div class="px-4 py-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $session['user_name'] }}</p>
                                @if (! empty($session['is_online']))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                        <i class="fas fa-circle text-[6px]"></i>{{ __('Online') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 dark:bg-zinc-800/30 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">
                                        <i class="fas fa-circle text-[6px]"></i>{{ __('Offline') }}
                                    </span>
                                @endif
                                @if ($session['is_current'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                        <i class="fas fa-circle text-[6px]"></i>{{ __('Current') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ ($session['browser'] ?? ucfirst($session['device_type'] ?? __('unknown'))) }}{{ $session['platform'] ? ' · ' . $session['platform'] : '' }}</p>
                        </div>
                        <button type="button" wire:click="revokeSession('{{ $session['id'] }}')"
                                class="flex-shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                            <i class="fas fa-right-from-bracket mr-1"></i>{{ __('Log out') }}
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                            <div class="text-zinc-500 uppercase tracking-wide">{{ __('IP') }}</div>
                            <div class="mt-1 font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $session['ip_address'] ?? __('N/A') }}</div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-900/40 px-3 py-2">
                            <div class="text-zinc-500 uppercase tracking-wide">{{ __('Last Seen') }}</div>
                            <div class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">{{ $lastSeen ? $lastSeen->translatedFormat('M d, h:i:s A') : '—' }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-zinc-400">
                    <i class="fas fa-satellite mb-2 block text-2xl"></i>
                    {{ __('No active sessions found.') }}
                </div>
            @endforelse
        </div>
    </div>


    {{-- ═══════════════════════════════ MODALS ════════════════════════════════ --}}

    {{-- ── Create Account Modal ──────────────────────────────────────── --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 px-4 py-8">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Create account') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Set up a new user profile with login credentials and language preference.') }}</p>
                    </div>
                    <button type="button" wire:click="closeCreateModal"
                            class="rounded-full p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit="createAccount" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <flux:input wire:model="createUser.name" :label="__('Full name')" type="text" required />
                    </div>
                    <div>
                        <flux:input wire:model="createUser.username" :label="__('Username')" type="text" required />
                    </div>
                    <div>
                        <flux:input wire:model="createUser.password" :label="__('Password')" type="password" required viewable />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-info-circle mr-1"></i>{{ __('At least 6 characters') }}
                        </p>
                        @error('createUser.password') <p class="mt-1 text-xs text-red-600 dark:text-red-400"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="createUser.password_confirmation" :label="__('Confirm password')" type="password" required viewable />
                        @error('createUser.password_confirmation') <p class="mt-1 text-xs text-red-600 dark:text-red-400"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Language') }}
                        </label>
                        <select wire:model="createUser.lang"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-zinc-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="en">{{ __('English') }}</option>
                            <option value="zh">{{ __('Chinese') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-shield-halved mr-1"></i>{{ __('Role') }}
                        </label>
                        <select wire:model="createUser.role"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-zinc-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="staff">{{ __('Staff') }} — {{ __('Limited access') }}</option>
                            <option value="admin">{{ __('Admin') }} — {{ __('Full access') }}</option>
                            <option value="warehouse">倉庫管理員 — 倉儲模組管理</option>
                            <option value="branch">分店人員 — 分店庫存查詢</option>
                        </select>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ __('Staff accounts will be prompted to set their own password on first login.') }}
                        </p>
                    </div>
                    <div class="md:col-span-2 mt-2 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <flux:button type="button" variant="filled" wire:click="closeCreateModal">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Create account') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── Delete Confirm Modal ──────────────────────────────────────── --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 px-4 py-8">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Delete account') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('This removes the account, its sessions, and its remembered devices.') }}</p>
                    </div>
                    <button type="button" wire:click="closeDeleteModal"
                            class="rounded-full p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-200">
                    {{ __('Are you sure you want to delete') }}
                    <span class="font-semibold">{{ $selectedUserName }}</span>?
                    {{ __('This action cannot be undone.') }}
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button type="button" variant="filled" wire:click="closeDeleteModal">{{ __('Cancel') }}</flux:button>
                    <flux:button type="button" variant="danger" wire:click="deleteAccount">
                        <i class="fas fa-trash-can mr-1.5"></i>{{ __('Delete account') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

</div>
