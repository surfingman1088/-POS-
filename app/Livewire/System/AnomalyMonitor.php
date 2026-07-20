<?php

namespace App\Livewire\System;

use App\Models\AnomalyLog;
use App\Models\AuditLogs;
use App\Models\User;
use App\Services\System\LineNotifyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class AnomalyMonitor extends Component
{
    use WithPagination;

    // 篩選
    public string $filterLevel    = '';
    public string $filterUser     = '';
    public string $filterResolved = '0'; // 0=未處理, 1=已處理, ''=全部
    public string $dateFrom       = '';
    public string $dateTo         = '';

    // 詳情 Modal
    public ?int  $viewingId   = null;
    public bool  $showDetail  = false;
    public string $resolveNote = '';

    protected $queryString = ['filterLevel', 'filterUser', 'filterResolved'];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    // ── 查看詳情 ──────────────────────────────────────────
    public function viewDetail(int $id): void
    {
        $this->viewingId   = $id;
        $this->resolveNote = '';
        $this->showDetail  = true;
    }

    // ── 標記為已處理 ──────────────────────────────────────
    public function markResolved(int $id): void
    {
        AnomalyLog::where('id', $id)->update([
            'resolved'     => true,
            'resolve_note' => $this->resolveNote ?: null,
        ]);
        $this->showDetail = false;
        $this->dispatch('toast', type: 'success', message: '已標記為已處理');
    }

    // ── 手動重新推播 ──────────────────────────────────────
    public function resendNotify(int $id): void
    {
        $log = AnomalyLog::with('user')->findOrFail($id);
        $lineNotify = app(LineNotifyService::class);

        $sent = $lineNotify->sendAnomalyAlert(
            storeName:    $log->store_name ?? config('app.name'),
            employeeName: $log->user?->name ?? '未知員工',
            anomalyType:  $log->title,
            detail:       $log->detail,
            time:         $log->created_at->setTimezone('Asia/Taipei')->format('Y/m/d H:i:s'),
        );

        if ($sent) {
            $log->update(['notified' => true]);
            $this->dispatch('toast', type: 'success', message: '已重新推播至 LINE');
        } else {
            $this->dispatch('toast', type: 'error', message: '推播失敗，請確認 LINE 設定');
        }
    }

    // ── 統計數字 ──────────────────────────────────────────
    public function getStatsProperty(): array
    {
        $today = now()->startOfDay();
        return [
            'total_today'    => AnomalyLog::where('created_at', '>=', $today)->count(),
            'high_today'     => AnomalyLog::where('level', 'high')->where('created_at', '>=', $today)->count(),
            'unresolved'     => AnomalyLog::where('resolved', false)->count(),
            'unnotified'     => AnomalyLog::where('notified', false)->where('level', '!=', 'low')->count(),
        ];
    }

    // ── 員工行為摘要（今日） ──────────────────────────────
    public function getEmployeeSummaryProperty(): \Illuminate\Support\Collection
    {
        return AuditLogs::with('user')
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as action_count'), DB::raw('MAX(created_at) as last_action'))
            ->groupBy('user_id')
            ->orderByDesc('action_count')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $anomalyCount = AnomalyLog::where('user_id', $row->user_id)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();
                return [
                    'user'          => $row->user,
                    'action_count'  => $row->action_count,
                    'last_action'   => $row->last_action,
                    'anomaly_count' => $anomalyCount,
                ];
            });
    }

    public function render()
    {
        $anomalies = AnomalyLog::with(['user', 'auditLog'])
            ->when($this->filterLevel, fn($q) => $q->where('level', $this->filterLevel))
            ->when($this->filterUser,  fn($q) => $q->where('user_id', $this->filterUser))
            ->when($this->filterResolved !== '', fn($q) => $q->where('resolved', (bool) $this->filterResolved))
            ->when($this->dateFrom, fn($q) => $q->where('created_at', '>=', $this->dateFrom . ' 00:00:00'))
            ->when($this->dateTo,   fn($q) => $q->where('created_at', '<=', $this->dateTo . ' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(20);

        $users = User::orderBy('name')->get(['id', 'name']);

        $viewingLog = $this->viewingId
            ? AnomalyLog::with(['user', 'auditLog'])->find($this->viewingId)
            : null;

        return view('livewire.system.anomaly-monitor', compact('anomalies', 'users', 'viewingLog'));
    }
}
