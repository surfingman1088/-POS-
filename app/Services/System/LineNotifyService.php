<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Messaging API 推播服務
 * 直接推播訊息到老闆的 LINE User ID
 */
class LineNotifyService
{
    private string $channelAccessToken;
    private string $ownerUserId;

    public function __construct()
    {
        $this->channelAccessToken = config('storeconfig.line_channel_access_token', '');
        $this->ownerUserId        = config('storeconfig.line_owner_user_id', '');
    }

    /**
     * 傳送純文字訊息給老闆
     */
    public function sendToOwner(string $message): bool
    {
        if (empty($this->channelAccessToken) || empty($this->ownerUserId)) {
            Log::warning('[LineNotify] 未設定 LINE_CHANNEL_ACCESS_TOKEN 或 LINE_OWNER_USER_ID，跳過推播');
            return false;
        }

        try {
            $response = Http::withToken($this->channelAccessToken)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to'       => $this->ownerUserId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('[LineNotify] 推播失敗', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('[LineNotify] 推播例外', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 傳送異常警報訊息（格式化）
     */
    public function sendAnomalyAlert(
        string $storeName,
        string $employeeName,
        string $anomalyType,
        string $detail,
        string $time
    ): bool {
        $message = implode("\n", [
            '🚨 【YoPOS 異常警報】',
            '─────────────────',
            "🏪 分店：{$storeName}",
            "👤 員工：{$employeeName}",
            "⚠️  類型：{$anomalyType}",
            "📋 說明：{$detail}",
            "🕐 時間：{$time}",
            '─────────────────',
            '請至後台查看詳情',
        ]);

        return $this->sendToOwner($message);
    }
}
