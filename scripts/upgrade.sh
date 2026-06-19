#!/bin/bash
# ============================================================
#  YO 團購 POS 系統 - VPS 一鍵升級腳本
#  使用方式：bash upgrade.sh
#  說明：從 GitHub 拉取最新代碼，並更新所有分店
# ============================================================
set -e

MAIN_REPO="/opt/yo-pos-source"
APP_BASE_DIR="/var/www"
MYSQL_ROOT_PASS="YoPOS2026Secure!"
BACKUP_DIR="/root/pos-backups/$(date '+%Y%m%d_%H%M%S')"

# 分店列表（與 install.sh 保持一致）
BRANCHES=(
    "bade|八德店|8001"
    "sanxia|三峽店|8002"
    "dazhu|大竹店|8003"
    "linkou|林口店|8004"
    "yiwen|藝文店|8005"
    "guolin|菓林店|8006"
)

echo "=========================================="
echo "  YO 團購 POS 系統 - 升級腳本"
echo "  時間: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="

# ---- 備份資料庫 ----
echo "[1/4] 備份資料庫..."
mkdir -p "$BACKUP_DIR"

for BRANCH_INFO in "${BRANCHES[@]}"; do
    IFS='|' read -r BRANCH_ID BRANCH_NAME PORT <<< "$BRANCH_INFO"
    DB_NAME="pos_${BRANCH_ID}"
    echo "  → 備份 ${BRANCH_NAME} 資料庫..."
    mysqldump -u root -p"${MYSQL_ROOT_PASS}" "${DB_NAME}" > "${BACKUP_DIR}/${DB_NAME}.sql" 2>/dev/null || echo "  ! 備份 ${DB_NAME} 失敗，跳過"
done
echo "  ✓ 資料庫備份完成，儲存於: ${BACKUP_DIR}"

# ---- 拉取最新代碼 ----
echo "[2/4] 從 GitHub 拉取最新代碼..."
cd "$MAIN_REPO"
git fetch origin
git reset --hard origin/main
echo "  ✓ 代碼更新完成"

# ---- 重新安裝依賴並編譯 ----
echo "[3/4] 更新依賴與重新編譯前端..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
npm install 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✓ 依賴更新完成"

# ---- 更新各分店 ----
echo "[4/4] 更新各分店..."

for BRANCH_INFO in "${BRANCHES[@]}"; do
    IFS='|' read -r BRANCH_ID BRANCH_NAME PORT <<< "$BRANCH_INFO"
    APP_DIR="${APP_BASE_DIR}/pos-${BRANCH_ID}"

    if [ ! -d "$APP_DIR" ]; then
        echo "  ! ${BRANCH_NAME} 目錄不存在，跳過（請執行 install.sh）"
        continue
    fi

    echo "  → 更新 ${BRANCH_NAME}..."

    # 備份現有 .env
    cp "${APP_DIR}/.env" "${BACKUP_DIR}/.env.${BRANCH_ID}" 2>/dev/null || true

    # 同步代碼（保留 .env 和 storage）
    rsync -a --exclude='.env' --exclude='storage/' --exclude='public/storage' \
        "${MAIN_REPO}/" "${APP_DIR}/"

    # 還原 .env
    cp "${BACKUP_DIR}/.env.${BRANCH_ID}" "${APP_DIR}/.env" 2>/dev/null || true

    # 設定權限
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 755 "$APP_DIR"
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

    # 執行 Laravel 升級指令
    cd "$APP_DIR"
    php artisan migrate --force 2>&1 | tail -3
    php artisan config:cache 2>&1 | tail -1
    php artisan route:cache 2>&1 | tail -1
    php artisan view:cache 2>&1 | tail -1

    echo "  ✓ ${BRANCH_NAME} 更新完成"
done

# 偵測 PHP 版本並重啟 FPM
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
systemctl restart php${PHP_VER}-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
nginx -t && systemctl reload nginx

echo ""
echo "=========================================="
echo "  🎉 升級完成！"
echo "  備份位置: ${BACKUP_DIR}"
echo "=========================================="
