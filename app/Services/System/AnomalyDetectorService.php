<?php

namespace App\Services\System;

use App\Models\AnomalyLog;
use App\Models\AuditLogs;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 異常操作偵測服務
 *
 * 偵測規則：
 * 1. order.deleted          → 訂單刪除（高風險）
 * 2. order.refunded         → 退款金額異常（> 閾值）
 * 3. order.cancelled        → 短時間內大量取消（> 3筆/小時）
 * 4. order.updated (折扣)   → 大額折扣（折扣率 > 30%）
 * 5. auth.failed_login      → 連續登入失敗（> 5次/10分鐘）
 * 6. account.deleted        → 帳號刪除
 * 7. product.deleted        → 商品刪除
 * 8. product.stock_adjusted → 庫存大幅調整（> 100）
 * 9. 非上班時間操作          → 凌晨 00:00–06:00 有操作
 * 10. order.created (大額)  → 單筆訂單金額 > 閾值
 */
class AnomalyDetectorService
{
    // ── 異常等級 ──────────────────────────────────────────
    const LEVEL_HIGH   = 'high';    // 立即推播
    const LEVEL_MEDIUM = 'medium';  // 推播
    const LEVEL_LOW    = 'low';     // 僅記錄

    // ── 閾值設定 ──────────────────────────────────────────
    const REFUND_ALERT_AMOUNT         = 1000;   // 退款超過此金額觸發
    const LARGE_ORDER_AMOUNT          = 5000;   // 單筆訂單超過此金額觸發
    const DISCOUNT_RATE_ALERT         = 0.30;   // 折扣率超過 30% 觸發
    const CANCEL_BURST_COUNT          = 3;      // 1小時內取消超過此數量觸發
    const FAILED_LOGIN_BURST_COUNT    = 5;      // 10分鐘內失敗超過此次數觸發
    const STOCK_ADJUST_ALERT_QUANTITY = 100;    // 庫存調整超過此數量觸發
    const OFF_HOUR_START              = 0;      // 非上班時間開始（0點）
    const OFF_HOUR_END                = 6;      // 非上班時間結束（6點）

    public function __construct(
        private readonly LineNotifyService $lineNotify
    ) {}

    /**
     * 主入口：分析一筆 AuditLog，判斷是否異常
     */
    public function analyze(AuditLogs $log): void
    {
        $anomaly = match (true) {
            $log->action === 'order.deleted'                          => $this->checkOrderDeleted($log),
            $log->action === 'order.refunded'                         => $this->checkOrderRefunded($log),
            $log->action === 'order.cancelled'                        => $this->checkOrderCancelBurst($log),
            $log->action === 'order.updated'                          => $this->checkLargeDiscount($log),
            $log->action === 'order.created'                          => $this->checkLargeOrder($log),
            $log->action === 'auth.failed_login'                      => $this->checkFailedLoginBurst($log),
            $log->action === 'account.deleted'                        => $this->checkAccountDeleted($log),
            $log->action === 'product.deleted'                        => $this->checkProductDeleted($log),
            $log->action === 'product.stock_adjusted'                 => $this->checkStockAdjust($log),
            default                                                   => null,
        };

        // 額外：非上班時間操作（任何 order.* 動作）
        if (!$anomaly && str_starts_with($log->action, 'order.')) {
            $anomaly = $this->checkOffHourOperation($log);
        }

        if ($anomaly) {
            $this->saveAndNotify($anomaly, $log);
        }
    }

    // ── 規則 1：訂單刪除 ──────────────────────────────────
    private function checkOrderDeleted(AuditLogs $log): ?array
    {
        $snapshot = $log->old_values ?? [];
        return [
            'type'    => 'order.deleted',
            'level'   => self::LEVEL_HIGH,
            'title'   => '訂單遭刪除',
            'detail'  => sprintf(
                '訂單 %s 被刪除（金額：NT$ %s）',
                $snapshot['receipt_number'] ?? '未知',
                number_format($snapshot['order_total'] ?? 0)
            ),
        ];
    }

    // ── 規則 2：退款金額異常 ──────────────────────────────
    private function checkOrderRefunded(AuditLogs $log): ?array
    {
        $newValues    = $log->new_values ?? [];
        $refundAmount = (float) ($newValues['refund_amount'] ?? 0);

        if ($refundAmount < self::REFUND_ALERT_AMOUNT) return null;

        return [
            'type'    => 'order.refunded',
            'level'   => self::LEVEL_HIGH,
            'title'   => '大額退款',
            'detail'  => sprintf(
                '訂單 %s 退款 NT$ %s',
                $newValues['receipt_number'] ?? '未知',
                number_format($refundAmount)
            ),
        ];
    }

    // ── 規則 3：短時間大量取消 ────────────────────────────
    private function checkOrderCancelBurst($log): ?array
    {
        if (!$log->user_id) return null;

        $count = AuditLogs::where('user_id', $log->user_id)
            ->where('action', 'order.cancelled')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($count < self::CANCEL_BURST_COUNT) return null;

        return [
            'type'    => 'order.cancel_burst',
            'level'   => self::LEVEL_MEDIUM,
            'title'   => '短時間大量取消訂單',
            'detail'  => sprintf('1 小時內已取消 %d 筆訂單', $count),
        ];
    }

    // ── 規則 4：大額折扣 ──────────────────────────────────
    private function checkLargeDiscount(AuditLogs $log): ?array
    {
        $newValues = $log->new_values ?? [];
        $oldValues = $log->old_values ?? [];

        // 只在折扣相關欄位有變動時觸發
        if (!isset($newValues['discount_value']) && !isset($newValues['discount_type'])) {
            return null;
        }

        $discountType  = $newValues['discount_type'] ?? 'none';
        $discountValue = (float) ($newValues['discount_value'] ?? 0);
        $orderTotal    = (float) ($newValues['order_total'] ?? $oldValues['order_total'] ?? 0);

        if ($discountType === 'none' || $discountValue <= 0) return null;

        // 計算折扣率
        $discountRate = 0;
        if ($discountType === 'percentage') {
            $discountRate = $discountValue / 100;
        } elseif ($discountType === 'fixed' && $orderTotal > 0) {
            $discountRate = $discountValue / $orderTotal;
        }

        if ($discountRate < self::DISCOUNT_RATE_ALERT) return null;

        return [
            'type'    => 'order.large_discount',
            'level'   => self::LEVEL_MEDIUM,
            'title'   => '大額折扣',
            'detail'  => sprintf(
                '訂單 %s 套用 %.0f%% 折扣（NT$ %s）',
                $newValues['receipt_number'] ?? '未知',
                $discountRate * 100,
                number_format($discountValue)
            ),
        ];
    }

    // ── 規則 5：連續登入失敗 ──────────────────────────────
    private function checkFailedLoginBurst(AuditLogs $log): ?array
    {
        $ip = $log->ip_address;
        if (!$ip) return null;

        $count = AuditLogs::where('ip_address', $ip)
            ->where('action', 'auth.failed_login')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($count < self::FAILED_LOGIN_BURST_COUNT) return null;

        return [
            'type'    => 'auth.brute_force',
            'level'   => self::LEVEL_HIGH,
            'title'   => '疑似暴力破解登入',
            'detail'  => sprintf('IP %s 在 10 分鐘內登入失敗 %d 次', $ip, $count),
        ];
    }

    // ── 規則 6：帳號刪除 ──────────────────────────────────
    private function checkAccountDeleted(AuditLogs $log): ?array
    {
        $snapshot = $log->old_values ?? [];
        return [
            'type'    => 'account.deleted',
            'level'   => self::LEVEL_HIGH,
            'title'   => '員工帳號遭刪除',
            'detail'  => sprintf(
                '帳號「%s」（%s）被刪除',
                $snapshot['name'] ?? '未知',
                $snapshot['username'] ?? '未知'
            ),
        ];
    }

    // ── 規則 7：商品刪除 ──────────────────────────────────
    private function checkProductDeleted(AuditLogs $log): ?array
    {
        $snapshot = $log->old_values ?? [];
        return [
            'type'    => 'product.deleted',
            'level'   => self::LEVEL_MEDIUM,
            'title'   => '商品遭刪除',
            'detail'  => sprintf(
                '商品「%s」（NT$ %s）被刪除',
                $snapshot['name'] ?? '未知',
                number_format($snapshot['price'] ?? 0)
            ),
        ];
    }

    // ── 規則 8：庫存大幅調整 ──────────────────────────────
    private function checkStockAdjust(AuditLogs $log): ?array
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];

        $oldStock = (int) ($oldValues['stocks'] ?? 0);
        $newStock = (int) ($newValues['stocks'] ?? 0);
        $diff     = abs($newStock - $oldStock);

        if ($diff < self::STOCK_ADJUST_ALERT_QUANTITY) return null;

        $direction = $newStock > $oldStock ? '增加' : '減少';

        return [
            'type'    => 'product.stock_large_adjust',
            'level'   => self::LEVEL_MEDIUM,
            'title'   => '庫存大幅調整',
            'detail'  => sprintf(
                '商品「%s」庫存%s %d（%d → %d）',
                $newValues['name'] ?? '未知',
                $direction,
                $diff,
                $oldStock,
                $newStock
            ),
        ];
    }

    // ── 規則 9：非上班時間操作 ────────────────────────────
    private function checkOffHourOperation(AuditLogs $log): ?array
    {
        $hour = (int) $log->created_at->format('H');

        if ($hour < self::OFF_HOUR_START || $hour >= self::OFF_HOUR_END) {
            return null;
        }

        return [
            'type'    => 'off_hour_operation',
            'level'   => self::LEVEL_LOW,
            'title'   => '非上班時間操作',
            'detail'  => sprintf(
                '凌晨 %02d:%02d 執行了 %s 操作',
                $hour,
                (int) $log->created_at->format('i'),
                $log->action
            ),
        ];
    }

    // ── 規則 10：大額訂單 ─────────────────────────────────
    private function checkLargeOrder(AuditLogs $log): ?array
    {
        $newValues   = $log->new_values ?? [];
        $orderTotal  = (float) ($newValues['order_total'] ?? 0);

        if ($orderTotal < self::LARGE_ORDER_AMOUNT) return null;

        return [
            'type'    => 'order.large_amount',
            'level'   => self::LEVEL_LOW,
            'title'   => '大額訂單',
            'detail'  => sprintf(
                '訂單 %s 金額 NT$ %s',
                $newValues['receipt_number'] ?? '未知',
                number_format($orderTotal)
            ),
        ];
    }

    // ── 儲存異常記錄並推播 ────────────────────────────────
    private function saveAndNotify(array $anomaly, AuditLogs $log): void
    {
        try {
            // 避免重複：同一 audit_log_id 只記錄一次
            $exists = AnomalyLog::where('audit_log_id', $log->id)->exists();
            if ($exists) return;

            $anomalyLog = AnomalyLog::create([
                'audit_log_id'  => $log->id,
                'user_id'       => $log->user_id,
                'anomaly_type'  => $anomaly['type'],
                'level'         => $anomaly['level'],
                'title'         => $anomaly['title'],
                'detail'        => $anomaly['detail'],
                'store_name'    => config('app.name'),
                'notified'      => false,
            ]);

            // HIGH 和 MEDIUM 等級立即推播
            if (in_array($anomaly['level'], [self::LEVEL_HIGH, self::LEVEL_MEDIUM])) {
                $employee = $log->user?->name ?? '未知員工';
                $storeName = config('storeconfig.store_name', config('app.name'));

                $sent = $this->lineNotify->sendAnomalyAlert(
                    storeName:    $storeName,
                    employeeName: $employee,
                    anomalyType:  $anomaly['title'],
                    detail:       $anomaly['detail'],
                    time:         $log->created_at->setTimezone('Asia/Taipei')->format('Y/m/d H:i:s'),
                );

                if ($sent) {
                    $anomalyLog->update(['notified' => true]);
                }
            }

        } catch (\Exception $e) {
            Log::error('[AnomalyDetector] 儲存異常記錄失敗', ['error' => $e->getMessage()]);
        }
    }
}
