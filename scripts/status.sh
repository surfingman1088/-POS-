#!/bin/bash
# ============================================================
#  YO 團購 POS 系統 - 系統狀態檢查腳本
#  使用方式：bash status.sh
# ============================================================

SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')

echo "=========================================="
echo "  YO 團購 POS 系統 - 狀態檢查"
echo "  時間: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# 服務狀態
echo "【系統服務狀態】"
for SERVICE in nginx mysql; do
    STATUS=$(systemctl is-active $SERVICE 2>/dev/null)
    if [ "$STATUS" = "active" ]; then
        echo "  ✓ $SERVICE: 運行中"
    else
        echo "  ✗ $SERVICE: 停止（狀態: $STATUS）"
    fi
done

# PHP-FPM
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "unknown")
FPM_STATUS=$(systemctl is-active php${PHP_VER}-fpm 2>/dev/null)
if [ "$FPM_STATUS" = "active" ]; then
    echo "  ✓ php${PHP_VER}-fpm: 運行中"
else
    echo "  ✗ php${PHP_VER}-fpm: 停止"
fi

echo ""
echo "【各分店狀態】"
PORTS=(8001 8002 8003 8004 8005 8006)
NAMES=("八德店" "三峽店" "大竹店" "林口店" "藝文店" "菓林店")

for i in "${!PORTS[@]}"; do
    PORT="${PORTS[$i]}"
    NAME="${NAMES[$i]}"
    HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 http://localhost:$PORT 2>/dev/null)
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
        echo "  ✓ ${NAME} (Port ${PORT}): 正常 [HTTP ${HTTP_CODE}]"
        echo "    網址: http://${SERVER_IP}:${PORT}"
    else
        echo "  ✗ ${NAME} (Port ${PORT}): 異常 [HTTP ${HTTP_CODE:-無回應}]"
    fi
done

echo ""
echo "【磁碟使用狀況】"
df -h / | tail -1 | awk '{print "  根目錄: 已用 "$3" / 總計 "$2" ("$5" 使用率)"}'

echo ""
echo "【記憶體使用狀況】"
free -h | grep Mem | awk '{print "  記憶體: 已用 "$3" / 總計 "$2}'

echo ""
echo "【最新備份】"
LATEST_BACKUP=$(ls -t /root/pos-backups/ 2>/dev/null | head -1)
if [ -n "$LATEST_BACKUP" ]; then
    echo "  最新備份: /root/pos-backups/${LATEST_BACKUP}"
else
    echo "  尚無備份記錄"
fi
