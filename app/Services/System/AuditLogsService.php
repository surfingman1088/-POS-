<?php

namespace App\Services\System;

use App\Models\AuditLogs;
use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Centralized service for handling all user action logs and audits.
class AuditLogsService
{
    private const TEMP_DEVICE_COOKIE = 'temporary_device_token';
    private const TEMP_DEVICE_SESSION_TOKEN = 'temporary_device_token';
    private const TEMP_DEVICE_SESSION_EXPIRES_AT = 'temporary_device_expires_at';

    public function record(
        string $action,
        ?User $user = null,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null
    ): AuditLogs {
        $request ??= request();

        $log = new AuditLogs();
        $log->user_id = $user?->id ?? Auth::id();
        $log->action = $action;
        $log->old_values = $oldValues ?: null;
        $log->new_values = $newValues ?: null;
        $log->ip_address = $request?->ip();
        $log->user_agent = substr((string) $request?->userAgent(), 0, 1000);

        if ($auditable) {
            $log->auditable()->associate($auditable);
        }

        $log->save();

        // ── 異常偵測 ─────────────────────────────────────
        try {
            app(\App\Services\System\AnomalyDetectorService::class)->analyze($log);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[AnomalyDetector] 分析失敗', ['error' => $e->getMessage(), 'action' => $action]);
        }
        // ── 異常偵測結束 ─────────────────────────────────

        return $log;
    }

    public function recordLogin(User $user, Request $request): AuditLogs
    {
        $device = $this->deviceSnapshot($request);

        $this->syncCurrentSession($user, $request);
        $this->upsertDevice($user, $request, 'login');

        return $this->record('auth.login', $user, null, [], $device, $request);
    }

    public function recordLogout(?User $user, Request $request): ?AuditLogs
    {
        if (! $user) {
            return null;
        }

        $device = $this->deviceSnapshot($request);
        $this->upsertDevice($user, $request, 'logout');

        return $this->record('auth.logout', $user, null, [], $device, $request);
    }

    public function recordRequest(User $user, Request $request): ?AuditLogs
    {
        if (! $this->shouldTrackRequest($request)) {
            return null;
        }

        $action = $this->requestAction($request);
        $context = $this->requestContext($request);
        $this->touchTemporaryDevice($user, $request);
        $this->syncCurrentSession($user, $request);

        return $this->record($action, $user, null, [], $context, $request);
    }

    public function recordAccountCreated(User $actor, User $account, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'account.created',
            $actor,
            $account,
            [],
            [
                'name' => $account->name,
                'username' => $account->username,
            ],
            $request
        );
    }

    public function recordAccountDeleted(User $actor, User $account, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'account.deleted',
            $actor,
            $account,
            [
                'name' => $account->name,
                'username' => $account->username,
            ],
            [],
            $request
        );
    }

    public function recordSessionRevoked(User $actor, array $session, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'session.revoked',
            $actor,
            null,
            $session,
            [],
            $request
        );
    }

    public function recordDeviceRemoved(User $actor, array $device, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'device.removed',
            $actor,
            null,
            $device,
            [],
            $request
        );
    }

    public function issueTemporaryDeviceToken(User $user, Request $request): string
    {
        $plainToken = (string) Str::random(64);
        $days = (int) config('storeconfig.session_expire_days', 30);
        // expire at end of the day N days from now
        $expiresAt = now()->addDays($days)->endOfDay();
        $this->storeTemporaryDevice($user, $request, $plainToken, $expiresAt);

        $minutes = max(1, now()->diffInMinutes($expiresAt));
        Cookie::queue(cookie(
            self::TEMP_DEVICE_COOKIE,
            $plainToken,
            $minutes,
            '/',
            null,
            false,
            true,
            false,
            'lax'
        ));

        $request->session()->put(self::TEMP_DEVICE_SESSION_TOKEN, $plainToken);
        $request->session()->put(self::TEMP_DEVICE_SESSION_EXPIRES_AT, $expiresAt->timestamp);

        return $plainToken;
    }

    public function revokeTemporaryDeviceToken(Request $request): void
    {
        $plainToken = $request->session()->pull(self::TEMP_DEVICE_SESSION_TOKEN) ?: $request->cookie(self::TEMP_DEVICE_COOKIE);

        if ($plainToken) {
            DB::table('remember_devices')
                ->where('token', $this->hashDeviceToken($plainToken))
                ->update([
                    'expires_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $request->session()->forget(self::TEMP_DEVICE_SESSION_EXPIRES_AT);
        Cookie::queue(Cookie::forget(self::TEMP_DEVICE_COOKIE));
    }

    public function isTemporaryDeviceExpired(Request $request): bool
    {
        $expiresAt = $request->session()->get(self::TEMP_DEVICE_SESSION_EXPIRES_AT);

        if (! $expiresAt) {
            return false;
        }

        return now()->timestamp >= (int) $expiresAt;
    }

    public function restoreTemporaryDevice(User $user, Request $request): void
    {
        $plainToken = $request->cookie(self::TEMP_DEVICE_COOKIE);

        if (! $plainToken) {
            return;
        }

        $device = DB::table('remember_devices')
            ->where('user_id', $user->id)
            ->where('token', $this->hashDeviceToken($plainToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            Cookie::queue(Cookie::forget(self::TEMP_DEVICE_COOKIE));
            $request->session()->forget([
                self::TEMP_DEVICE_SESSION_TOKEN,
                self::TEMP_DEVICE_SESSION_EXPIRES_AT,
            ]);

            return;
        }

        $request->session()->put(self::TEMP_DEVICE_SESSION_TOKEN, $plainToken);
        $request->session()->put(self::TEMP_DEVICE_SESSION_EXPIRES_AT, Carbon::parse($device->expires_at)->timestamp);
        $this->touchTemporaryDevice($user, $request, $plainToken);
    }

    public function dashboardMetrics(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(29)->startOfDay();
        $to ??= now()->endOfDay();

        $query = $this->auditQuery($from, $to);

        $logins = (clone $query)->where('action', 'auth.login')->count();
        $logouts = (clone $query)->where('action', 'auth.logout')->count();
        $failedLogins = (clone $query)->where('action', 'auth.failed_login')->count();
        $accountsCreated = (clone $query)->where('action', 'account.created')->count();
        $accountsDeleted = (clone $query)->where('action', 'account.deleted')->count();
        $sessionsRevoked = (clone $query)->where('action', 'session.revoked')->count();
        $devicesRemoved = (clone $query)->where('action', 'device.removed')->count();
        $actions = (clone $query)->count();
        $uniqueUsers = (clone $query)->whereNotNull('user_id')->distinct()->count('user_id');
        $uniqueDevices = DB::table('remember_devices')->where('expires_at', '>', now())->count();

        $actionLabels = [];
        $actionsByDay = [];
        $authTrendLabels = [];
        $authLoginsByWeek = [];
        $authLogoutsByWeek = [];
        $authFailedByWeek = [];
        $activityHourLabels = [];
        $activityByHour = [];
        $deviceTypeLabels = [];
        $deviceTypes = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $actionLabels[] = Carbon::parse($day)->locale(app()->getLocale())->isoFormat('MMM D');
            $actionsByDay[] = (int) (clone $query)->whereDate('created_at', $day)->count();
        }

        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = (clone $weekStart)->endOfWeek();

            $authTrendLabels[] = $weekStart->locale(app()->getLocale())->isoFormat('MMM D');
            $authLoginsByWeek[] = (int) (clone $query)->where('action', 'auth.login')->whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $authLogoutsByWeek[] = (int) (clone $query)->where('action', 'auth.logout')->whereBetween('created_at', [$weekStart, $weekEnd])->count();
            $authFailedByWeek[] = (int) (clone $query)->where('action', 'auth.failed_login')->whereBetween('created_at', [$weekStart, $weekEnd])->count();
        }

        for ($hour = 0; $hour < 24; $hour++) {
            $activityHourLabels[] = Carbon::createFromTime($hour, 0)->locale(app()->getLocale())->isoFormat('h A');
            $activityByHour[] = (int) (clone $query)->whereRaw('HOUR(created_at) = ?', [$hour])->count();
        }

        $deviceRows = DB::table('remember_devices')
            ->where('expires_at', '>', now())
            ->select('user_agent')
            ->get();

        foreach ($deviceRows as $row) {
            $type = $this->deviceTypeFromUserAgent((string) ($row->user_agent ?? ''));
            $deviceTypes[$type] = ($deviceTypes[$type] ?? 0) + 1;
        }

        // Keep device type labels as keys (desktop/mobile/tablet/bot)
        // JS will localize display labels using the i18n map.
        foreach (array_keys($deviceTypes) as $type) {
            $deviceTypeLabels[] = $type;
        }

        $actionBreakdownLabels = [
            __('Logins'),
            __('Logouts'),
            __('Failed Logins'),
            __('Accounts Created'),
            __('Accounts Deleted'),
            __('Sessions Revoked'),
            __('Devices Removed'),
        ];

        $actionBreakdownValues = [
            $logins,
            $logouts,
            $failedLogins,
            $accountsCreated,
            $accountsDeleted,
            $sessionsRevoked,
            $devicesRemoved,
        ];

        return [
            'actions' => $actions,
            'logins' => $logins,
            'logouts' => $logouts,
            'failed_logins' => $failedLogins,
            'accounts_created' => $accountsCreated,
            'accounts_deleted' => $accountsDeleted,
            'sessions_revoked' => $sessionsRevoked,
            'devices_removed' => $devicesRemoved,
            'security_events' => $failedLogins + $sessionsRevoked + $devicesRemoved,
            'unique_users' => $uniqueUsers,
            'unique_devices' => $uniqueDevices,
            'action_labels' => $actionLabels,
            'actions_by_day' => $actionsByDay,
            'auth_trend_labels' => $authTrendLabels,
            'auth_logins_by_week' => $authLoginsByWeek,
            'auth_logouts_by_week' => $authLogoutsByWeek,
            'auth_failed_by_week' => $authFailedByWeek,
            'activity_hour_labels' => $activityHourLabels,
            'activity_by_hour' => $activityByHour,
            'device_type_labels' => $deviceTypeLabels,
            'device_types' => $deviceTypes,
            'action_breakdown_labels' => $actionBreakdownLabels,
            'action_breakdown_values' => $actionBreakdownValues,
        ];
    }

    public function recentLogs(int $limit = 20, ?Carbon $from = null, ?Carbon $to = null, ?string $action = null): Collection
    {
        return AuditLogs::with('user')
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->latest()
            ->take($limit)
            ->get();
    }

    public function accountSummaries(?int $userId = null): Collection
    {
        return User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->withCount('auditLogs')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $lastLogin = AuditLogs::query()
                    ->where('user_id', $user->id)
                    ->where('action', 'auth.login')
                    ->latest()
                    ->first();

                $deviceCount = DB::table('remember_devices')
                    ->where('expires_at', '>', now())
                    ->where('user_id', $user->id)
                    ->count();

                $sessionCount = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->count();

                return [
                    'user' => $user,
                    'actions_count' => $user->audit_logs_count,
                    'last_login_at' => $lastLogin?->created_at,
                    'device_count' => $deviceCount,
                    'session_count' => $sessionCount,
                ];
            });
    }

    public function sessionsForUsers(?int $userId = null): Collection
    {
        return DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->when($userId, fn ($query) => $query->where('sessions.user_id', $userId))
            ->orderByDesc('sessions.last_activity')
            ->get([
                'sessions.id',
                'sessions.user_id',
                'users.name as user_name',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
            ])
            ->map(function ($session) {
                $isOnline = ((int) now()->timestamp - (int) $session->last_activity) <= ((int) config('session.lifetime', 120) * 60);

                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'user_name' => $session->user_name,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    // last_activity is a Unix timestamp (UTC). Create from UTC then convert
                    // to the application timezone to avoid showing UTC times.
                    'last_seen_at' => Carbon::createFromTimestampUTC((int) $session->last_activity)->setTimezone(config('app.timezone')),
                    'device_type' => $this->deviceTypeFromUserAgent((string) $session->user_agent),
                    'browser' => $this->browserFromUserAgent((string) $session->user_agent),
                    'platform' => $this->platformFromUserAgent((string) $session->user_agent),
                    'is_online' => $isOnline,
                ];
            });
    }

    public function devicesForUsers(?int $userId = null): Collection
    {
        return DB::table('remember_devices')
            ->leftJoin('users', 'remember_devices.user_id', '=', 'users.id')
            ->where('remember_devices.expires_at', '>', now())
            ->when($userId, fn ($query) => $query->where('remember_devices.user_id', $userId))
            ->orderByDesc('remember_devices.last_used_at')
            ->get([
                'remember_devices.id',
                'remember_devices.user_id',
                'users.name as user_name',
                'remember_devices.browser',
                'remember_devices.expires_at',
                'remember_devices.platform',
                'remember_devices.ip_address',
                'remember_devices.user_agent',
                'remember_devices.last_used_at',
                'remember_devices.token',
            ])
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'user_id' => $device->user_id,
                    'user_name' => $device->user_name,
                    'browser' => $device->browser,
                    'expires_at' => $device->expires_at ? Carbon::parse($device->expires_at) : null,
                    'platform' => $device->platform,
                    'ip_address' => $device->ip_address,
                    'user_agent' => $device->user_agent,
                    'last_used_at' => $device->last_used_at ? Carbon::parse($device->last_used_at) : null,
                    'device_type' => $this->deviceTypeFromUserAgent((string) $device->user_agent),
                ];
            });
    }

    protected function shouldTrackRequest(Request $request): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if ($request->is('logs') || $request->is('logs/*')) {
            return false;
        }

        if ($request->isMethod('get')) {
            return false;
        }

        // For Livewire, only track meaningful write operations
        if ($request->is('livewire/update')) {
            $method = strtolower(
                data_get($request->input('components', []), '0.calls.0.method') ?? ''
            );

            // Skip read-only / UI-only methods
            $skipPrefixes = [
                'open', 'close', 'view', 'load', 'set', 'get',
                'show', 'hide', 'toggle', 'clear', 'reset',
                'updated', 'check', 'poll', 'sort', 'search',
                'refresh', 'render', 'paginate',
            ];

            foreach ($skipPrefixes as $prefix) {
                if (str_starts_with($method, $prefix)) {
                    return false;
                }
            }

            // Also skip empty or wire:navigate calls
            if (empty($method) || $method === 'update') {
                return false;
            }
        }

        return true;
    }

    protected function requestAction(Request $request): string
    {
        if ($request->is('livewire/update')) {
            $component = data_get($request->input('components', []), '0.snapshot.memo.name')
                ?? data_get($request->input('components', []), '0.memo.name')
                ?? 'livewire';
            $method = data_get($request->input('components', []), '0.calls.0.method')
                ?? data_get($request->input('components', []), '0.updates.0.payload.method')
                ?? 'update';

            return 'livewire.' . $component . '.' . $method;
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            return 'http.' . $request->method() . '.' . $routeName;
        }

        return 'http.' . $request->method() . '.' . trim($request->path(), '/');
    }

    protected function requestContext(Request $request): array
    {
        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'device_type' => $this->deviceTypeFromUserAgent((string) $request->userAgent()),
            'browser' => $this->browserFromUserAgent((string) $request->userAgent()),
            'platform' => $this->platformFromUserAgent((string) $request->userAgent()),
        ];
    }

    protected function deviceSnapshot(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->deviceTypeFromUserAgent((string) $request->userAgent()),
            'browser' => $this->browserFromUserAgent((string) $request->userAgent()),
            'platform' => $this->platformFromUserAgent((string) $request->userAgent()),
        ];
    }

    protected function syncCurrentSession(User $user, Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $request->session()->getId())
            ->update([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity' => now()->timestamp,
            ]);
    }

    protected function upsertDevice(User $user, Request $request, string $context): void
    {
        $plainToken = $request->session()->get(self::TEMP_DEVICE_SESSION_TOKEN) ?: $request->cookie(self::TEMP_DEVICE_COOKIE);

        if (! $plainToken) {
            return;
        }

        $this->storeTemporaryDevice(
            $user,
            $request,
            $plainToken,
            Carbon::createFromTimestamp((int) ($request->session()->get(self::TEMP_DEVICE_SESSION_EXPIRES_AT) ?? now()->addDays((int) config('storeconfig.session_expire_days', 30))->endOfDay()->timestamp))
        );
    }

    protected function touchTemporaryDevice(User $user, Request $request, ?string $plainToken = null): void
    {
        $plainToken ??= $request->session()->get(self::TEMP_DEVICE_SESSION_TOKEN) ?: $request->cookie(self::TEMP_DEVICE_COOKIE);

        if (! $plainToken) {
            return;
        }

        DB::table('remember_devices')
            ->where('user_id', $user->id)
            ->where('token', $this->hashDeviceToken($plainToken))
            ->where('expires_at', '>', now())
            ->update([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function storeTemporaryDevice(User $user, Request $request, string $plainToken, Carbon $expiresAt): void
    {
        DB::table('remember_devices')->updateOrInsert(
            [
                'user_id' => $user->id,
                'token' => $this->hashDeviceToken($plainToken),
            ],
            [
                'browser' => $this->browserFromUserAgent((string) $request->userAgent()),
                'platform' => $this->platformFromUserAgent((string) $request->userAgent()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => $expiresAt,
                'last_used_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected function deviceTypeFromUserAgent(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'ipad'), str_contains($ua, 'tablet') => 'tablet',
            str_contains($ua, 'mobi'), str_contains($ua, 'android'), str_contains($ua, 'iphone') => 'mobile',
            str_contains($ua, 'bot'), str_contains($ua, 'crawl'), str_contains($ua, 'spider') => 'bot',
            default => 'desktop',
        };
    }

    protected function browserFromUserAgent(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'edg/') => 'Edge',
            str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'chrome/') && ! str_contains($ua, 'edg/') => 'Chrome',
            str_contains($ua, 'firefox/') => 'Firefox',
            str_contains($ua, 'safari/') => 'Safari',
            default => 'Unknown',
        };
    }

    protected function platformFromUserAgent(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Unknown',
        };
    }

    protected function deviceLabelFromUserAgent(string $userAgent): string
    {
        return ucfirst($this->deviceTypeFromUserAgent($userAgent)) . ' - ' . $this->browserFromUserAgent($userAgent);
    }

    protected function hashDeviceToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    protected function auditQuery(Carbon $from, Carbon $to): QueryBuilder
    {
        return DB::table('audit_logs')->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Below are example methods for recording specific actions in the system.
     * You can expand this with more methods for other models and actions as needed.
     * Each method prepares the relevant context and calls the generic record() method to create the audit log entry.
     */
    // ── Orders ────────────────────────────────────────────────────────────────

    public function recordOrderCreated(User $actor, Order $order, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.created',
            $actor,
            $order,
            [],
            [
                'receipt_number' => $order->receipt_number,
                'order_type'     => $order->order_type,
                'order_total'    => $order->order_total,
                'payment_type'   => $order->payment_type,
                'payment_status' => $order->payment_status,
                'status'         => $order->status,
            ],
            $request
        );
    }

    public function recordOrderUpdated(User $actor, Order $order, array $oldValues, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.updated',
            $actor,
            $order,
            $oldValues,
            [
                'receipt_number' => $order->receipt_number,
                'order_type'     => $order->order_type,
                'order_total'    => $order->order_total,
                'payment_type'   => $order->payment_type,
                'payment_status' => $order->payment_status,
                'status'         => $order->status,
            ],
            $request
        );
    }

    public function recordOrderDeleted(User $actor, array $orderSnapshot, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.deleted',
            $actor,
            null,
            $orderSnapshot,
            [],
            $request
        );
    }

    public function recordOrderCancelled(User $actor, Order $order, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.cancelled',
            $actor,
            $order,
            ['previous_status' => $order->getOriginal('status')],
            ['receipt_number'  => $order->receipt_number],
            $request
        );
    }

    public function recordPaymentConfirmed(User $actor, Order $order, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.payment_confirmed',
            $actor,
            $order,
            ['payment_status' => 'unpaid'],
            [
                'receipt_number'  => $order->receipt_number,
                'payment_status'  => 'paid',
                'payment_type'    => $order->payment_type,
                'amount_received' => $order->amount_received,
            ],
            $request
        );
    }

    public function recordOrderRefunded(User $actor, Order $order, float $refundAmount, array $refundedItems, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'order.refunded',
            $actor,
            $order,
            ['payment_status' => 'paid'],
            [
                'receipt_number' => $order->receipt_number,
                'refund_amount'  => $refundAmount,
                'items'          => $refundedItems,
            ],
            $request
        );
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    public function recordCustomerCreated(User $actor, Customer $customer, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'customer.created',
            $actor,
            $customer,
            [],
            [
                'name'           => $customer->name,
                'unit'           => $customer->unit,
                'address'        => $customer->address,
                'contact_number' => $customer->contact_number,
            ],
            $request
        );
    }

    public function recordCustomerUpdated(User $actor, Customer $customer, array $oldValues, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'customer.updated',
            $actor,
            $customer,
            $oldValues,
            [
                'name'           => $customer->name,
                'unit'           => $customer->unit,
                'address'        => $customer->address,
                'contact_number' => $customer->contact_number,
            ],
            $request
        );
    }

    public function recordCustomerDeleted(User $actor, array $snapshot, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'customer.deleted',
            $actor,
            null,
            $snapshot,
            [],
            $request
        );
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    public function recordEmployeeCreated(User $actor, Employee $employee, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'employee.created',
            $actor,
            $employee,
            [],
            [
                'name'           => $employee->name,
                'contact_number' => $employee->contact_number,
                'status'         => $employee->status,
            ],
            $request
        );
    }

    public function recordEmployeeUpdated(User $actor, Employee $employee, array $oldValues, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'employee.updated',
            $actor,
            $employee,
            $oldValues,
            [
                'name'           => $employee->name,
                'contact_number' => $employee->contact_number,
                'status'         => $employee->status,
            ],
            $request
        );
    }

    public function recordEmployeeArchived(User $actor, Employee $employee, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'employee.archived',
            $actor,
            $employee,
            ['name' => $employee->name, 'is_archived' => false],
            ['name' => $employee->name, 'is_archived' => true],
            $request
        );
    }

    public function recordEmployeeRestored(User $actor, Employee $employee, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'employee.restored',
            $actor,
            $employee,
            ['name' => $employee->name, 'is_archived' => true],
            ['name' => $employee->name, 'is_archived' => false],
            $request
        );
    }

    public function recordEmployeeDeleted(User $actor, array $snapshot, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'employee.deleted',
            $actor,
            null,
            $snapshot,
            [],
            $request
        );
    }

    // ── Products ──────────────────────────────────────────────────────────────

    public function recordProductCreated(User $actor, Product $product, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'product.created',
            $actor,
            $product,
            [],
            [
                'name'        => $product->name,
                'category'    => $product->category,
                'price'       => $product->price,
                'stocks'      => $product->stocks,
                'is_in_stock' => $product->is_in_stock,
            ],
            $request
        );
    }

    public function recordProductUpdated(User $actor, Product $product, array $oldValues, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'product.updated',
            $actor,
            $product,
            $oldValues,
            [
                'name'        => $product->name,
                'category'    => $product->category,
                'price'       => $product->price,
                'stocks'      => $product->stocks,
                'is_in_stock' => $product->is_in_stock,
            ],
            $request
        );
    }

    public function recordProductStockAdjusted(User $actor, Product $product, int $oldStocks, int $newStocks, string $reason, ?Request $request = null): AuditLogs
    {
        $direction = $newStocks > $oldStocks ? 'added' : 'removed';
        $amount    = abs($newStocks - $oldStocks);

        return $this->record(
            'product.stock_adjusted',
            $actor,
            $product,
            ['stocks' => $oldStocks],
            [
                'stocks'    => $newStocks,
                'direction' => $direction,
                'amount'    => $amount,
                'reason'    => $reason,
                'name'      => $product->name,
            ],
            $request
        );
    }

    public function recordProductArchived(User $actor, Product $product, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'product.archived',
            $actor,
            $product,
            ['name' => $product->name, 'is_in_stock' => true],
            ['name' => $product->name, 'is_in_stock' => false],
            $request
        );
    }

    public function recordProductRestored(User $actor, Product $product, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'product.restored',
            $actor,
            $product,
            ['name' => $product->name, 'is_in_stock' => false],
            ['name' => $product->name, 'is_in_stock' => true],
            $request
        );
    }

    public function recordProductDeleted(User $actor, array $snapshot, ?Request $request = null): AuditLogs
    {
        return $this->record(
            'product.deleted',
            $actor,
            null,
            $snapshot,
            [],
            $request
        );
    }
}
