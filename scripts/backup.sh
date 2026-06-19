#!/bin/bash
# ============================================================
#  YO 團購 POS 系統 - 資料庫備份腳本
#  使用方式：bash backup.sh
#  建議設定每日自動備份：crontab -e
#  加入：0 2 * * * bash /opt/yo-pos-source/scripts/backup.sh
# ============================================================

MYSQL_ROOT_PASS="YoPOS2026Secure!"
BACKUP_DIR="/root/pos-backups/$(date '+%Y%m%d_%H%M%S')"
KEEP_DAYS=7  # 保留最近 7 天的備份

BRANCHES=(
    "bade|八德店"
    "sanxia|三峽店"
    "dazhu|大竹店"
    "linkou|林口店"
    "yiwen|藝文店"
    "guolin|菓林店"
)

echo "=========================================="
echo "  YO 團購 POS 系統 - 資料庫備份"
echo "  時間: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="

mkdir -p "$BACKUP_DIR"

for BRANCH_INFO in "${BRANCHES[@]}"; do
    IFS='|' read -r BRANCH_ID BRANCH_NAME <<< "$BRANCH_INFO"
    DB_NAME="pos_${BRANCH_ID}"

    echo "  → 備份 ${BRANCH_NAME}..."
    if mysqldump -u root -p"${MYSQL_ROOT_PASS}" "${DB_NAME}" 2>/dev/null | gzip > "${BACKUP_DIR}/${DB_NAME}.sql.gz"; then
        SIZE=$(du -sh "${BACKUP_DIR}/${DB_NAME}.sql.gz" | cut -f1)
        echo "  ✓ ${BRANCH_NAME} 備份完成（${SIZE}）"
    else
        echo "  ✗ ${BRANCH_NAME} 備份失敗"
    fi
done

# 清除舊備份
echo ""
echo "清除 ${KEEP_DAYS} 天前的舊備份..."
find /root/pos-backups/ -maxdepth 1 -type d -mtime +${KEEP_DAYS} -exec rm -rf {} \; 2>/dev/null || true

echo ""
echo "備份完成！儲存位置：${BACKUP_DIR}"
echo "備份清單："
ls -lh "${BACKUP_DIR}/"
