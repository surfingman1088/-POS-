<?php

namespace App\Livewire\Logs;

use App\Models\User;
use App\Services\System\AuditLogsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Livewire\Component;

class Users extends Component
{
    public string $userFilter = 'all';
    public bool $showCreateModal = false;
    public bool $showDeleteModal = false;
    public ?int $selectedUserId = null;
    public ?string $selectedUserName = null;

    public array $createUser = [
        'name'                  => '',
        'username'              => '',
        'password'              => '',
        'password_confirmation' => '',
        'lang'                  => 'en',
        'role'                  => 'staff',
    ];

    public array $users    = [];
    public array $accounts = [];
    public array $sessions = [];
    public array $devices  = [];
    public array $metrics  = [];

    public function mount(AuditLogsService $auditLogsService): void
    {
        $this->users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();

        $this->loadData($auditLogsService);
    }

    public function updatedUserFilter(): void
    {
        $this->loadData(app(AuditLogsService::class));
    }

    // ── Create modal ────────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createAccount(AuditLogsService $auditLogsService): void
    {
        $validated = $this->validate([
            'createUser.name'     => ['required', 'string', 'max:255'],
            'createUser.username' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) {
                    if (User::whereRaw('LOWER(username) = LOWER(?)', [ucwords($value)])->exists()) {
                        $fail(__('The username has already been taken.'));
                    }
                },
            ],
            'createUser.password' => [
                'required', 'string', 'min:6', 'confirmed',
            ],
            'createUser.lang'     => ['required', 'in:en,zh'],
            'createUser.role'     => ['required', 'in:admin,staff'],
        ]);

        $isStaff = $validated['createUser']['role'] === 'staff';

        $account = User::create([
            'name'                  => ucwords($validated['createUser']['name']),
            'username'              => ucwords($validated['createUser']['username']),
            'password'              => Hash::make($validated['createUser']['password']),
            'lang'                  => $validated['createUser']['lang'],
            'role'                  => $validated['createUser']['role'],
            // 員工帳號建立後，強制首次登入修改密碼
            'must_change_password'  => $isStaff,
        ]);

        $auditLogsService->recordAccountCreated(Auth::user(), $account, request());

        $this->showCreateModal = false;
        $this->loadData($auditLogsService);
        session()->flash('success', __('Account created successfully.'));
    }

    // ── Delete modal ────────────────────────────────────────────────────────

    public function confirmDeleteAccount(int $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->selectedUserId   = $user->id;
        $this->selectedUserName = $user->name;
        $this->showDeleteModal  = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal  = false;
        $this->selectedUserId   = null;
        $this->selectedUserName = null;
    }

    public function deleteAccount(AuditLogsService $auditLogsService): void
    {
        $user = User::find($this->selectedUserId);

        if (! $user) {
            $this->closeDeleteModal();
            return;
        }

        $auditLogsService->recordAccountDeleted(Auth::user(), $user, request());

        DB::table('remember_devices')->where('user_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        $this->closeDeleteModal();
        $this->loadData($auditLogsService);
        session()->flash('success', __('Account deleted successfully.'));

        // If admin deleted their own account, log them out too
        if (Auth::id() === $user->id) {
            $this->performLogout($auditLogsService);
        }
    }

    // ── Device / session actions ─────────────────────────────────────────────

    public function removeDevice(int $deviceId, AuditLogsService $auditLogsService): void
    {
        $device = DB::table('remember_devices')->where('id', $deviceId)->first();
        if (! $device) {
            return;
        }

        $auditLogsService->recordDeviceRemoved(Auth::user(), (array) $device, request());
        DB::table('remember_devices')->where('id', $deviceId)->delete();
        $this->loadData($auditLogsService);
    }

    public function revokeSession(string $sessionId, AuditLogsService $auditLogsService): void
    {
        $session = DB::table('sessions')->where('id', $sessionId)->first();
        if (! $session) {
            return;
        }

        // Revoking the current session means logging out
        if ($sessionId === request()->session()->getId()) {
            $this->performLogout($auditLogsService);
            return;
        }

        $auditLogsService->recordSessionRevoked(Auth::user(), [
            'session_id' => $session->id,
            'user_id'    => $session->user_id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
        ], request());

        DB::table('sessions')->where('id', $sessionId)->delete();
        $this->loadData($auditLogsService);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function performLogout(AuditLogsService $auditLogsService): void
    {
        $auditLogsService->revokeTemporaryDeviceToken(request());
        $auditLogsService->recordLogout(Auth::user(), request());

        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function resetCreateForm(): void
    {
        $this->createUser = [
            'name'                  => '',
            'username'              => '',
            'password'              => '',
            'password_confirmation' => '',
            'lang'                  => 'en',
            'role'                  => 'staff',
        ];
        $this->resetValidation();
    }

    protected function loadData(AuditLogsService $auditLogsService): void
    {
        $userId = $this->userFilter !== 'all' ? (int) $this->userFilter : null;

        // Detect current remembered device via cookie token
        $currentDeviceToken = request()->cookie('temporary_device_token');
        $currentDeviceId    = null;
        if ($currentDeviceToken) {
            $row = DB::table('remember_devices')
                ->where('token', hash('sha256', $currentDeviceToken))
                ->value('id');
            $currentDeviceId = $row ?: null;
        }

        // Serialize every Carbon date → ISO string so Livewire JSON round-trips safely.
        $this->accounts = $auditLogsService->accountSummaries($userId)
            ->map(fn ($a) => [
                'user_id'              => $a['user']->id,
                'name'                 => $a['user']->name,
                'username'             => $a['user']->username,
                'role'                 => $a['user']->role ?? 'admin',
                'must_change_password' => $a['user']->must_change_password ?? false,
                'actions_count'        => $a['actions_count'],
                'device_count'         => $a['device_count'],
                'session_count'        => $a['session_count'],
                'last_login_at'        => $a['last_login_at']?->toIso8601String(),
            ])
            ->all();

        $this->sessions = $auditLogsService->sessionsForUsers($userId)
            ->map(fn ($s) => [
                'id'               => $s['id'],
                'user_id'          => $s['user_id'],
                'user_name'        => $s['user_name'],
                'ip_address'       => $s['ip_address'],
                'user_agent'       => $s['user_agent'],
                'device_type'      => $s['device_type'],
                'browser'          => $s['browser'] ?? null,
                'platform'         => $s['platform'] ?? null,
                'is_online'        => $s['is_online'] ?? false,
                'is_current'       => $s['id'] === request()->session()->getId(),
                'last_seen_at'     => isset($s['last_seen_at'])
                    ? Carbon::parse($s['last_seen_at'])->toIso8601String()
                    : null,
            ])
            ->all();

        $onlineUserIds = collect($this->sessions)
            ->filter(fn (array $session) => ! empty($session['is_online']))
            ->pluck('user_id')
            ->unique()
            ->all();

        $this->devices = $auditLogsService->devicesForUsers($userId)
            ->map(fn ($d) => [
                'id'               => $d['id'],
                'user_id'          => $d['user_id'],
                'user_name'        => $d['user_name'],
                'browser'          => $d['browser'],
                'platform'         => $d['platform'],
                'ip_address'       => $d['ip_address'],
                'user_agent'       => $d['user_agent'],
                'device_type'      => $d['device_type'],
                'is_current'       => $d['id'] === $currentDeviceId,
                'last_used_at'     => isset($d['last_used_at'])
                    ? Carbon::parse($d['last_used_at'])->toIso8601String()
                    : null,
                'expires_at'       => isset($d['expires_at'])
                    ? Carbon::parse($d['expires_at'])->toIso8601String()
                    : null,
            ])
            ->all();

        $this->metrics = [
            'accounts'     => count($this->accounts),
            'sessions'     => count($this->sessions),
            'devices'      => count($this->devices),
            'online_users' => count($onlineUserIds),
            'active_users' => collect($this->accounts)
                ->filter(fn ($a) => ! empty($a['session_count']) || ! empty($a['device_count']))
                ->count(),
        ];

        $this->accounts = collect($this->accounts)
            ->map(fn (array $account) => [
                ...$account,
                'is_online' => in_array($account['user_id'], $onlineUserIds, true),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.logs.users');
    }
}
